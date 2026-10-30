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

// Resolves and persists the authoritative flags for one convocatoria row, or
// returns false when the convocatoria cannot be fetched.
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
            return false;
        }
        $flags = authoritative_flags($client, $base_url, $child, $cache);
        if ($flags === null) {
            return false;
        }
        $new_padre = $flags['convocatoria_padre_id'];
        $new_publicar = (int)$flags['publicar_opec'];
        $new_historico = (int)$flags['opec_historico'];
    } else {
        // Re-check the classification source (parent, or self when parentless).
        $source_id = $old_padre !== null ? $old_padre : $code;
        $flags = source_flags($client, $base_url, $source_id, $cache);
        if ($flags === null) {
            return false;
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

    return true;
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
