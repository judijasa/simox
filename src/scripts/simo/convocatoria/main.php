<?php
require 'vendor/autoload.php';
require __DIR__ . '/helper.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Crawler\HttpClient;
use Utils\Logger;

// Authoritative publico/historico classifier. The parent convocatoria (or the
// convocatoria itself when parentless) is the sole authority for the two
// classification flags; the offer payload never carries them.
//
// backfill() classifies every convocatoria once (manual, one-shot): it resolves
// each convocatoria's parent and stores `publicar_opec`/`opec_historico`, then
// denormalizes `opec_historico` onto `empleo`. Re-running resumes at the rows
// still unclassified, so an interrupted run needs no cursor.
//
// update() is the steady-state cron: it re-checks the flags of every
// convocatoria currently classified `publicar_opec = 1` plus any new one. A
// flip `publicar_opec -> opec_historico` is the relocation event: it flips the
// convocatoria, bulk-fetches the relocated OPECs from the historico endpoint
// (`search_convocatoria=<parent>`) into `empleo_snapshot`, and re-denormalizes
// `empleo.opec_historico`.

#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function backfill($conn,
    $batch_size_limit = 200,
    $jobs_per_page = 50,
    $timeout = 60 * 60 * 24  // secs
){
    $base_url = "https://simo.cnsc.gov.co";
    $client = make_http_client();
    classify(
        $conn,
        $client,
        $base_url,
        'SELECT id, code, convocatoria_padre_id, publicar_opec, opec_historico
           FROM convocatoria
          WHERE publicar_opec IS NULL AND opec_historico IS NULL',
        false,
        $batch_size_limit,
        $jobs_per_page,
        $timeout
    );
}

#[CronJob(schedule: '0 4 * * *', scope: 'worker')]
#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function update($conn,
    $batch_size_limit = 200,
    $jobs_per_page = 50,
    $timeout = 60 * 45  // secs
){
    $base_url = "https://simo.cnsc.gov.co";
    $client = make_http_client();
    classify(
        $conn,
        $client,
        $base_url,
        'SELECT id, code, convocatoria_padre_id, publicar_opec, opec_historico
           FROM convocatoria
          WHERE publicar_opec = 1
             OR (publicar_opec IS NULL AND opec_historico IS NULL)',
        true,
        $batch_size_limit,
        $jobs_per_page,
        $timeout
    );
}

function make_http_client(){
    return new HttpClient([
        // The site serves an incomplete certificate chain and answers JSON only
        // when the request declares it.
        'ignore_ssl' => true,
        'timeout' => 60,
        'headers' => ['Accept: application/json'],
    ]);
}

// Classifies the convocatorias selected by $sql. When $relocate is true,
// every detected publico->historico flip also triggers the bulk historico
// fetch of its subtree; backfill (relocate=false) only fills the flags.
function classify($conn, HttpClient $client, $base_url, $sql, $relocate, $batch_size_limit, $jobs_per_page, $timeout){
    $cache = [];
    $rows = $conn->query($sql)->fetchAll();
    $total = count($rows);

    Logger::info("classifying $total convocatorias");
    $start_time = time();
    $classified = 0;
    $flipped_sources = [];
    foreach ($rows as $row) {
        if (time() - $start_time > $timeout) {
            Logger::info("WARNING: timeout stopped classification after $classified convocatorias.");
            break;
        }

        $result = classify_row($conn, $client, $base_url, $row, $cache);
        if ($result === null) {
            continue;
        }
        $classified++;
        if ($classified % 50 === 0) {
            Logger::info("progress: $classified/$total classified (" . (time() - $start_time) . "s)");
        }
        if ($result['flipped']) {
            $flipped_sources[$result['source_id']] = true;
        }
    }

    if ($relocate) {
        foreach (array_keys($flipped_sources) as $source_id) {
            fetch_relocations($conn, $base_url, (int)$source_id, $jobs_per_page, $batch_size_limit);
        }
    }

    propagate_empleo_flags($conn);
    Logger::info("Classified $classified of " . count($rows) . " convocatorias; "
        . count($flipped_sources) . " relocation source(s).");
}

// Resolves and persists the authoritative flags for one convocatoria row, or
// null when the convocatoria cannot be fetched. Returns whether this pass
// detected a publico->historico flip and the classification source (the parent,
// or self when parentless) to bulk-fetch on a flip.
function classify_row($conn, HttpClient $client, $base_url, array $row, array &$cache){
    $id = (int)$row['id'];
    $code = (int)$row['code'];
    $old_padre = $row['convocatoria_padre_id'] === null ? null : (int)$row['convocatoria_padre_id'];
    $old_publicar = $row['publicar_opec'] === null ? null : (int)$row['publicar_opec'];
    $old_historico = $row['opec_historico'] === null ? null : (int)$row['opec_historico'];

    if ($old_padre === null && $old_publicar === null && $old_historico === null) {
        // Never classified: resolve the parent from the child convocatoria
        // (2 calls; 1 when parentless).
        $child = fetch_convocatoria($client, $base_url, $code);
        if ($child === null) {
            return null;
        }
        $flags = authoritative_flags($client, $base_url, $child, $cache);
        if ($flags === null) {
            return null;
        }
        $new_padre = $flags['convocatoria_padre_id'];
        $new_publicar = (int)$flags['publicar_opec'];
        $new_historico = (int)$flags['opec_historico'];
    } else {
        // Re-check the classification source (parent, or self when parentless).
        $source_id = $old_padre !== null ? $old_padre : $code;
        $flags = source_flags($client, $base_url, $source_id, $cache);
        if ($flags === null) {
            return null;
        }
        $new_padre = $old_padre;
        $new_publicar = (int)$flags['publicar_opec'];
        $new_historico = (int)$flags['opec_historico'];
    }

    $stmt = $conn->prepare(
        'UPDATE convocatoria
            SET convocatoria_padre_id = :padre,
                publicar_opec = :publicar,
                opec_historico = :historico
          WHERE id = :id'
    );
    $stmt->execute([
        ':padre' => $new_padre,
        ':publicar' => $new_publicar,
        ':historico' => $new_historico,
        ':id' => $id,
    ]);

    return [
        'source_id' => $new_padre !== null ? $new_padre : $code,
        'flipped' => $old_publicar === 1 && $new_historico === 1,
    ];
}

// Denormalizes convocatoria.opec_historico onto empleo. The flag-updater is the
// sole writer of empleo.opec_historico; the pipeline leaves it at the default.
function propagate_empleo_flags($conn){
    $conn->exec(
        'UPDATE empleo e
            JOIN convocatoria c ON e.convocatoria_id = c.id
            SET e.opec_historico = COALESCE(c.opec_historico, 0)'
    );
}

// Bulk-fetches every OPEC under one classification source from the historico
// endpoint and persists them as historico snapshots. A single source (parent)
// covers all of its child convocatorias, so a flip is one paginated crawl.
function fetch_relocations($conn, $base_url, int $source_id, $jobs_per_page, $batch_size_limit){
    $base_api_request = $base_url
        . '/empleos/ofertaPublica/historico/?size=' . $jobs_per_page
        . '&search_convocatoria=' . $source_id;

    $batch = [];
    $batch_job_ids = [];
    $batch_size = 0;
    $total_saved = 0;

    for ($page = 0; ; $page++) {
        $new_jobs = get_api_data($base_api_request, $page);
        if ($new_jobs instanceof ArrayObject && count($new_jobs) === 0) {
            break;
        }

        $output = batch_with_new_jobs($batch, $batch_job_ids, $new_jobs);
        [$batch, $batch_job_ids, $added_jobs_n] = $output;
        $batch_size += $added_jobs_n;

        if ($batch_size >= $batch_size_limit) {
            persist_snapshots($conn, $batch, 'historico');
            $total_saved += $batch_size;
            $batch = [];
            $batch_job_ids = [];
            $batch_size = 0;
        }
    }

    if ($batch_size > 0) {
        persist_snapshots($conn, $batch, 'historico');
        $total_saved += $batch_size;
    }

    Logger::info("Relocation fetch for source $source_id: saved $total_saved historico snapshots.");
}
