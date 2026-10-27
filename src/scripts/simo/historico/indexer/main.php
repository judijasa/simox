<?php
require 'vendor/autoload.php';
require __DIR__ . '/../../publico/indexer/helper.php';

use Utils\Agent;
use Utils\Logger;

// Manual dump of the whole "historico" archive (empleos/ofertaPublica/historico).
// Unlike the publico indexer it has no stable sort order, so a cross-run page
// cursor cannot be trusted: each run pages the archive from 0 until the first
// empty page and relies on the (opec, source, fecha_inscripcion) unique key to
// dedupe. The archive is fully consumed once a full pass reports 0 new rows.
#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function backfill($conn,
    $batch_size_limit = 200,
    $jobs_per_page = 50,
    $timeout = 60 * 60 * 24  // secs
){
    $base_url = "https://simo.cnsc.gov.co";
    historico_indexer($conn, $base_url, $batch_size_limit, $jobs_per_page, $timeout);
}

function historico_indexer($conn, $base_url, $batch_size_limit, $jobs_per_page, $timeout){
    $batch = array();
    $batch_job_ids = array();
    $batch_size = 0;

    $base_api_request = $base_url . '/empleos/ofertaPublica/historico/?size=' . $jobs_per_page;

    // Distinct historico rows before/after: the delta is this pass's new-row
    // count, so a re-run that reports 0 confirms the archive is fully consumed.
    $before = count_historico_rows($conn);

    $total_saved = 0;
    $start_time = time();
    $timed_out = false;
    for ($page = 0; ; $page++){
        if (time() - $start_time > $timeout) {
            $timed_out = true;
            break;
        }

        $new_jobs = get_api_data($base_api_request, $page);
        if ($new_jobs instanceof ArrayObject && count($new_jobs) === 0) {
            break;
        }

        $output = batch_with_new_jobs($batch, $batch_job_ids, $new_jobs);
        [$batch, $batch_job_ids, $added_jobs_n] = $output;
        $batch_size = $batch_size + $added_jobs_n;

        if ($batch_size >= $batch_size_limit) {
            persist_snapshots($conn, $batch, 'historico');
            $total_saved += $batch_size;
            Logger::info("Saved $batch_size historico jobs ($total_saved total, page $page).");
            $batch = [];
            $batch_job_ids = [];
            $batch_size = 0;
        }
    }

    // Flush the final partial batch (empty page or timeout).
    if ($batch_size > 0) {
        persist_snapshots($conn, $batch, 'historico');
        $total_saved += $batch_size;
        Logger::info("Saved $batch_size historico jobs ($total_saved total, page ".($page - 1).").");
    }

    if ($timed_out) {
        Logger::info("WARNING: timeout stopped historico crawl at page $page.");
    }

    $after = count_historico_rows($conn);
    $new_rows = $after - $before;
    Logger::info("Historico pass complete: $new_rows new rows ($after total). "
        . "Re-run until this reports 0 new rows to confirm the archive is fully consumed.");
}

function count_historico_rows($conn){
    $stmt = $conn->query("SELECT COUNT(*) FROM empleo_snapshot WHERE source = 'historico'");
    return (int)$stmt->fetchColumn();
}
