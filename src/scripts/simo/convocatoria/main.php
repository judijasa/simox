<?php
require 'vendor/autoload.php';
require __DIR__ . '/helper.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Crawler\HttpClient;
use Utils\DatabaseOps\GuardedInsert;
use Utils\Logger;

// Authoritative publico/historico classifier. Each convocatoria's own two
// classification flags (`publicarOpec`/`opecHistorico`) are authoritative; the
// offer payload never carries them. A child's parent is recorded as its own
// row (own flags), never resolved in place of the child's.
//
// backfill() classifies every convocatoria once (manual, one-shot): it resolves
// each convocatoria's own flags and parent link, stores
// `publicar_opec`/`opec_historico`/`descripcion`, records parents as rows, then
// denormalizes `opec_historico` onto `empleo`. Re-running resumes at the rows
// still unclassified, so an interrupted run needs no cursor.
//
// update() is the steady-state cron: it re-checks the flags of every
// convocatoria currently classified `publicar_opec = 1` plus any new one. A
// flip `publicar_opec -> opec_historico` is recorded on the convocatoria and
// re-denormalized onto `empleo.opec_historico`.
//
// This module never writes `empleo_snapshot`: that table belongs to the publico
// and historico indexers, its only crawl owners.

#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function backfill($conn,
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
        $timeout
    );
}

#[CronJob(schedule: '0 4 * * *', scope: 'worker')]
#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function update($conn,
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

// Classifies the convocatorias selected by $sql.
function classify($conn, HttpClient $client, $base_url, $sql, $timeout){
    $cache = [];
    $rows = $conn->query($sql)->fetchAll();
    $total = count($rows);

    Logger::info("classifying $total convocatorias");
    $start_time = time();
    $classified = 0;
    foreach ($rows as $row) {
        if (time() - $start_time > $timeout) {
            Logger::info("WARNING: timeout stopped classification after $classified convocatorias.");
            break;
        }

        if (classify_row($conn, $client, $base_url, $row, $cache) === false) {
            continue;
        }
        $classified++;
        if ($classified % 50 === 0) {
            Logger::info("progress: $classified/$total classified (" . (time() - $start_time) . "s)");
        }
    }

    propagate_empleo_flags($conn);
    Logger::info("Classified $classified of " . count($rows) . " convocatorias.");
}

// Resolves and persists a convocatoria's own flags (and its parent link) for
// one row, or returns false when the convocatoria cannot be fetched. A child's
// parent is recorded as its own row.
function classify_row($conn, HttpClient $client, $base_url, array $row, array &$cache){
    $id = (int)$row['id'];
    $code = (int)$row['code'];
    $old_padre = $row['convocatoria_padre_id'] === null ? null : (int)$row['convocatoria_padre_id'];
    $old_publicar = $row['publicar_opec'] === null ? null : (int)$row['publicar_opec'];
    $old_historico = $row['opec_historico'] === null ? null : (int)$row['opec_historico'];

    $conv = fetch_convocatoria($client, $base_url, $code);
    if ($conv === null) {
        return false;
    }

    if ($old_padre === null && $old_publicar === null && $old_historico === null) {
        // Never classified: resolve the parent link and own flags from the
        // child convocatoria.
        $flags = authoritative_flags($conv);
        $new_padre = $flags['convocatoria_padre_id'];
        $new_publicar = (int)$flags['publicar_opec'];
        $new_historico = (int)$flags['opec_historico'];
    } else {
        // Re-check the classification source (self).
        $flags = source_flags($conv);
        $new_padre = $old_padre;
        $new_publicar = (int)$flags['publicar_opec'];
        $new_historico = (int)$flags['opec_historico'];
    }

    $descripcion = $conv['descripcion'] ?? null;

    $stmt = $conn->prepare(
        'UPDATE convocatoria
            SET convocatoria_padre_id = :padre,
                publicar_opec = :publicar,
                opec_historico = :historico,
                descripcion = :descripcion
          WHERE id = :id'
    );
    $stmt->execute([
        ':padre' => $new_padre,
        ':publicar' => $new_publicar,
        ':historico' => $new_historico,
        ':descripcion' => $descripcion,
        ':id' => $id,
    ]);

    if ($new_padre !== null && !array_key_exists($new_padre, $cache)) {
        $cache[$new_padre] = true;
        $parent = fetch_convocatoria($client, $base_url, $new_padre);
        if ($parent !== null) {
            upsert_parent($conn, $parent);
        }
    }

    return true;
}

// Persists a parent convocatoria (or refreshes it) from a fetched API payload:
// its own flags, identity fields, and description. `convocatoria_padre_id` is
// whatever the API returns (null, or a grandparent id); no recursion is done.
//
// Two statements, not one upsert: a single `INSERT ... ON DUPLICATE KEY UPDATE`
// would evaluate `NEXT VALUE FOR convocatoria_id_seq` eagerly for every offered
// parent and burn a sequence id on each duplicate re-offer. The UPDATE first
// touches the row already present (matched by its unique `code`); the guarded
// INSERT then allocates a sequence id only for a genuinely-new parent.
function upsert_parent($conn, array $parent){
    $parent_id = $parent['id'] ?? null;
    if ($parent_id === null) {
        return;
    }

    $padre = $parent['convocatoriaPadre'] ?? null;
    $padre_id = is_array($padre) ? ($padre['id'] ?? null) : null;

    $code = (int)$parent_id;
    $nombre = $parent['nombre'] ?? null;
    $agno = $parent['agno'] ?? null;
    $codigo = $parent['codigo'] ?? null;
    $entidad = json_encode($parent['entidad'] ?? null);
    $padre_col = $padre_id === null ? null : (int)$padre_id;
    $publicar = (int)(bool)($parent['publicarOpec'] ?? false);
    $historico = (int)(bool)($parent['opecHistorico'] ?? false);
    $descripcion = $parent['descripcion'] ?? null;

    // Refresh first: touch the existing parent row (matched by its unique
    // `code`). For a brand-new parent this updates zero rows and the guarded
    // INSERT below allocates the id.
    $stmt = $conn->prepare(
        'UPDATE convocatoria
            SET nombre = :nombre,
                agno = :agno,
                codigo = :codigo,
                entidad = :entidad,
                convocatoria_padre_id = :padre,
                publicar_opec = :publicar,
                opec_historico = :historico,
                descripcion = :descripcion
          WHERE code = :code'
    );
    $stmt->execute([
        ':nombre' => $nombre,
        ':agno' => $agno,
        ':codigo' => $codigo,
        ':entidad' => $entidad,
        ':padre' => $padre_col,
        ':publicar' => $publicar,
        ':historico' => $historico,
        ':descripcion' => $descripcion,
        ':code' => $code,
    ]);

    // Then insert only a genuinely-new parent; the guard filters a re-offered
    // `code`, so `NEXT VALUE FOR convocatoria_id_seq` is evaluated only for a
    // row actually inserted (no burn on the duplicate path).
    GuardedInsert::insert(
        $conn,
        'convocatoria',
        ['id', 'code', 'nombre', 'agno', 'codigo', 'entidad', 'convocatoria_padre_id',
         'publicar_opec', 'opec_historico', 'descripcion'],
        ['code'],
        [[$code, $nombre, $agno, $codigo, $entidad, $padre_col, $publicar, $historico, $descripcion]],
        1,
        ['id' => 'NEXT VALUE FOR convocatoria_id_seq']
    );
}

// Denormalizes convocatoria.opec_historico onto empleo, one-way only (0 -> 1).
// The pipeline stamps the source-derived value at insert (publico -> 0,
// historico -> 1); this corrects the one case the source cannot see — an offer
// snapshotted as publico whose convocatoria has since flipped historical.
function propagate_empleo_flags($conn){
    $conn->exec(
        'UPDATE empleo e
            JOIN convocatoria c ON e.convocatoria_id = c.id
            SET e.opec_historico = 1
          WHERE c.opec_historico = 1'
    );
}
