<?php
require 'vendor/autoload.php';
require __DIR__ . '/../../publico/indexer/helper.php';

use Utils\Agent;
use Utils\Logger;

// Manual dump of the whole "historico" archive (empleos/ofertaPublica/historico).
// Unlike the publico indexer it has no stable sort order, so a durable cross-run
// page cursor cannot be trusted: each run pages the archive from 0 (or from an
// explicit $start_page, to resume a pass that aborted) until the first empty
// page, and relies on the (opec, source, fecha_inscripcion) unique key to
// dedupe. The archive is fully consumed once a pass that started at page 0
// reports 0 new rows: a resumed pass only covers the tail and cannot prove it.
// A page is retried twice before the pass aborts, because the endpoint
// intermittently answers 500 on a URL that succeeds moments later.
// To resume: phprun '.../backfill(start_page: <page>)'
#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function backfill($conn,
    $batch_size_limit = 200,
    $jobs_per_page = 50,
    $timeout = 60 * 60 * 24,  // secs
    $start_page = 0
){
    $base_url = "https://simo.cnsc.gov.co";
    historico_indexer($conn, $base_url, $batch_size_limit, $jobs_per_page, $timeout, $start_page);
}

function historico_indexer($conn, $base_url, $batch_size_limit, $jobs_per_page, $timeout, $start_page = 0){
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
    for ($page = $start_page; ; $page++){
        if (time() - $start_time > $timeout) {
            $timed_out = true;
            break;
        }

        try {
            $new_jobs = get_page_with_retries($base_api_request, $page);
        } catch (Throwable $e) {
            // Report the resume point first: it is the only state worth
            // carrying out of an aborted pass.
            Logger::info("Historico crawl failed at page $page: " . $e->getMessage()
                . " — resume with start_page=$page.");
            // The pending batch was never flushed; persist it so resuming at
            // this page does not silently drop it (idempotent via the unique key).
            if ($batch_size > 0) {
                persist_snapshots($conn, $batch, 'historico');
                $total_saved += $batch_size;
                Logger::info("Saved the pending $batch_size historico jobs ($total_saved total this pass).");
            }
            throw $e;
        }

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
        Logger::info("WARNING: timeout stopped historico crawl at page $page — resume with start_page=$page.");
    }

    $after = count_historico_rows($conn);
    $new_rows = $after - $before;
    if ($start_page > 0) {
        // A resumed pass only covers the tail, so it must not print the
        // completion signal: only a pass from page 0 proves completeness.
        Logger::info("Historico pass (resumed at page $start_page) complete: $new_rows new rows ($after total). "
            . "Only a pass from page 0 confirms the archive is fully consumed.");
    } else {
        Logger::info("Historico pass complete: $new_rows new rows ($after total). "
            . "Re-run until this reports 0 new rows to confirm the archive is fully consumed.");
    }
}

// Fetches one page, retrying a failure $retries more times with a doubling
// delay (2s, 4s by default). The endpoint intermittently answers 500 on a URL
// that succeeds moments later, so a single failure says nothing about the page;
// only a page that fails every attempt aborts the pass.
function get_page_with_retries($base_api_request, $page, $retries = 2, $delay = 2){
    for ($attempt = 0; ; $attempt++) {
        try {
            return get_api_data($base_api_request, $page);
        } catch (Throwable $e) {
            if ($attempt >= $retries) {
                throw $e;
            }
            $wait = $delay * (2 ** $attempt);
            Logger::info("Historico page $page failed (" . $e->getMessage() . ") — retry "
                . ($attempt + 1) . "/$retries in {$wait}s.");
            sleep($wait);
        }
    }
}

function count_historico_rows($conn){
    $stmt = $conn->query("SELECT COUNT(*) FROM empleo_snapshot WHERE source = 'historico'");
    return (int)$stmt->fetchColumn();
}
