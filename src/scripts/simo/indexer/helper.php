<?php
require 'vendor/autoload.php';

use Utils\DatabaseOps\GuardedInsert;

// Shared crawl primitives for the simo indexers: paginating the publico and
// historico endpoints and persisting their offers into `empleo_snapshot`.
// Both indexers (`indexer/publico`, `indexer/historico`) require this file.

function batch_with_new_jobs($batch, $batch_job_ids, $new_jobs){
    $new_batch = $batch;
    $new_batch_job_ids = $batch_job_ids;
    $added_jobs_n = 0;
    foreach($new_jobs as $job){
        $job_id = $job['id'];
        if (!in_array($job_id, $batch_job_ids, true)){
            $new_batch[] = $job;
            $new_batch_job_ids[] = $job_id;
            $added_jobs_n++;
        }
    }
    return [$new_batch, $new_batch_job_ids, $added_jobs_n];
}

function get_api_data($base_api_request, $page){
    // Example of url (includes search by departamento):
    // https://simo.cnsc.gov.co/empleos/ofertaPublica/?search_departamento=1&page=0&size=10

    $api_request = $base_api_request . '&page=' . "$page";

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $api_request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Do not verify certificates; the target site (simo.cnsc.gov.co)
    // is misconfigured; it returns incomplete certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);

    // Execute cURL session and get the response
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: $error");
    }

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close cURL session
    curl_close($ch);

    // Check if request was successful
    if ($httpCode >= 400) {
        throw new Exception("HTTP Error: $httpCode");
    }

    // Parse JSON response
    // true: returns assoc array
    // false: returns stdClass
    $data = new ArrayObject(json_decode($response, true));

    // Check for JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Error: " . json_last_error_msg());
    }
    return $data;
}

/**
 * Refreshes `last_seen` on empleo_snapshot rows already present in $keys.
 *
 * $keys is a list of [opec, source, fecha_inscripcion] triples (the table's
 * unique key). Rows not yet in the table are untouched; they get their
 * `last_seen` from the column DEFAULT when inserted afterwards.
 */
function refresh_last_seen($conn, array $keys){
    if ($keys === []) return;

    $first = 'SELECT ? AS opec, ? AS source, ? AS fecha_inscripcion FROM DUAL';
    $other = 'SELECT ?, ?, ? FROM DUAL';
    $selects = [$first];
    for ($i = 1, $n = count($keys); $i < $n; $i++) {
        $selects[] = $other;
    }
    $source = implode(' UNION ALL ', $selects);
    $sql = 'UPDATE empleo_snapshot s '
        . "JOIN ({$source}) v "
        . '  ON s.opec = v.opec AND s.source = v.source AND s.fecha_inscripcion = v.fecha_inscripcion '
        . 'SET s.last_seen = CURRENT_TIMESTAMP';
    $conn->prepare($sql)->execute(array_merge(...$keys));
}

function persist_snapshots($conn, $jobs, $source = 'publico'){
    $rows = [];
    $keys = [];
    foreach ($jobs as $job) {
        // fecha_inscripcion is NOT NULL DEFAULT '': the explicit empty
        // string (not SQL NULL) is what lets the (opec, source,
        // fecha_inscripcion) unique key collide, so coerce missing/null
        // to '' here.
        $fecha_inscripcion = $job['fechaInscripcion'] ?? '';
        $rows[] = [
            $job['id'],
            $source,
            json_encode($job['empleo']),
            $job['estadoInscripcion'],
            // cast boolean to int, otherwise PHP convert false to empty string
            (int)$job['favorito'],
            json_encode($job['inscripcionId']),
            $fecha_inscripcion,
            $job['nivelNombre'],
            json_encode($job['access']),
        ];
        $keys[] = [$job['id'], $source, $fecha_inscripcion];
    }
    if ($rows === []) return;

    // Two statements, not one upsert: a single `INSERT … ON DUPLICATE KEY
    // UPDATE` would evaluate `NEXT VALUE FOR` eagerly for every offered row and
    // re-burn the sequence on each duplicate. Splitting keeps the two effects
    // separate — the UPDATE touches only rows already present, and the guarded
    // INSERT allocates a sequence id only for genuinely-new rows. UPDATE runs
    // first so a new row still gets its `last_seen` from the column DEFAULT.
    //
    // Touch first: refresh last_seen for rows already present. The publico
    // crawler re-offers the whole set every run, so this is how we record
    // "last observed"; a removed job simply stops being touched and its
    // last_seen freezes (the removal signal).
    refresh_last_seen($conn, $keys);

    // Then insert only genuinely-new rows; the guard consumes a sequence id
    // only for rows actually inserted (id carries no default, so a naive
    // writer would fail 1364 — the guard is what stops sequence burn).
    GuardedInsert::insert(
        $conn,
        'empleo_snapshot',
        ['id', 'opec', 'source', 'empleo', 'estado_inscripcion', 'favorito',
         'inscripcion_id', 'fecha_inscripcion', 'nivel_nombre', 'access'],
        ['opec', 'source', 'fecha_inscripcion'],
        $rows,
        count($rows),
        ['id' => 'NEXT VALUE FOR empleo_snapshot_id_seq']
    );
}
