<?php
require 'vendor/autoload.php';
require __DIR__ . '/helper.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Crawler\HttpClient;
use Utils\Logger;

// Manual one-time dump of the whole "Avisos informativos" archive.
#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function backfill($conn,
    $batch_size_limit = 200,
    $timeout = 60 * 45  // secs
){
    $base_url = "https://www.cnsc.gov.co";
    indexer($conn, $base_url, false, $batch_size_limit, $timeout);
}

// Incremental cron: stops at the first page with no new node_id.
#[CronJob(schedule: '0 18 * * *', scope: 'worker')]
#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function update($conn,
    $batch_size_limit = 200,
    $timeout = 60 * 45  // secs
){
    $base_url = "https://www.cnsc.gov.co";
    indexer($conn, $base_url, true, $batch_size_limit, $timeout);
}

function indexer($conn, $base_url, $incremental, $batch_size_limit, $timeout){
    $client = new HttpClient([
        // The site serves an incomplete certificate chain.
        'ignore_ssl' => true,
        'timeout' => 60,
    ]);

    $batch = array();
    $batch_node_ids = array();
    $batch_size = 0;

    $total_saved = 0;
    $start_time = time();
    $timed_out = false;
    $page = 0;  // the site's pager is zero-based

    for (;; $page++) {
        if (time() - $start_time > $timeout) {
            $timed_out = true;
            break;
        }

        $node_ids = crawl_aviso_node_ids($client, $base_url, $page);
        if (count($node_ids) === 0) {
            break;
        }

        if ($incremental) {
            $node_ids = new_node_ids($conn, $node_ids);
            if (count($node_ids) === 0) {
                break;
            }
        }

        foreach ($node_ids as $node_id) {
            if (in_array($node_id, $batch_node_ids, true)) {
                continue;
            }

            $aviso = crawl_aviso($client, $base_url, $node_id);
            if ($aviso === null) {
                Logger::info("WARNING: could not parse aviso node $node_id.");
                continue;
            }

            $batch[] = $aviso;
            $batch_node_ids[] = $node_id;
            $batch_size++;
        }

        if ($batch_size >= $batch_size_limit) {
            persist_snapshots($conn, $batch);
            $total_saved += $batch_size;
            Logger::info("Saved $batch_size avisos ($total_saved total, page $page).");
            $batch = [];
            $batch_node_ids = [];
            $batch_size = 0;
        }
    }

    // Flush the final partial batch (empty page, known page or timeout).
    if ($batch_size > 0) {
        persist_snapshots($conn, $batch);
        $total_saved += $batch_size;
        Logger::info("Saved $batch_size avisos ($total_saved total, page ".($page - 1).").");
    }

    if ($timed_out) {
        Logger::info("WARNING: timeout stopped crawling at page $page.");
    }

    if ($total_saved == 0) {
        Logger::info("Nothing to save. Skipping db insertion.");
    }
}
