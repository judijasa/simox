<?php
require 'vendor/autoload.php';
require __DIR__ . '/helpers.php';
require 'src/utils/indexing.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Connectivity\Database;
use Utils\DatabaseOps\CursorSeq;
use Utils\Logger;

#[CronJob(schedule: 'daily')]
#[Agent]
function main($batch_size_limit = 200, $jobs_per_page = 50, $timeout =  60 * 45){
    $base_url = "https://simo.cnsc.gov.co";
    $dbname = 'simo';
    try {
        $conn = Database::admin($dbname);
        indexer(
            $conn,
            $base_url,
            $batch_size_limit,
            $jobs_per_page,
            $timeout);
    } catch (PDOException $e) {
        echo "Error: ". $e->getMessage(). PHP_EOL;
    } finally {
        $conn = null;
    }
}

function indexer($conn, $base_url, $batch_size_limit, $jobs_per_page, $timeout){
    $batch = array();
    $batch_job_ids = array();
    $batch_size = 0;

    $min_page = 1;

    $cursor_key = 'simo_indexer_cursorseq';
    $cursorseq = new CursorSeq($conn, $cursor_key);
    $page = $cursorseq->get_cursor($min_page);

    // Prepare API request
    $base_api_request = $base_url. '/empleos/ofertaPublica/?size='. $jobs_per_page;

    $total_saved = 0;
    $start_time = time();
    while(true){
        $new_jobs = get_api_data($base_api_request, $page);
        // Catch exactly the output when exceeding max page...
        if ($new_jobs instanceof ArrayObject && count($new_jobs) === 0) {
            $max_page = intdiv(get_total_job_offers($base_url), $jobs_per_page);
            if ($page >= $max_page){
                $page = $min_page;
                $new_jobs = get_api_data($base_api_request, $page);
            }else{
                // Notify anomaly with url and handle it accordingly
                break;
            }
        }
        $output = batch_with_new_jobs($batch, $batch_job_ids, $new_jobs);
        [$batch, $batch_job_ids, $added_jobs_n] = $output;
        $batch_size = $batch_size + $added_jobs_n;

        $cond1 = $batch_size >= $batch_size_limit;
        $cond2a = $batch_size > 0;
        $cond2b = time() - $start_time > $timeout;
        if ($cond1) {
            persist_snapshots($conn, $batch);
            $cursorseq->set_cursor($page);
            $total_saved += $batch_size;
            Logger::info("Saved $batch_size jobs ($total_saved total, page $page).");
            $batch = [];
            $batch_job_ids = [];
            $batch_size = 0;
        } elseif ($cond2a && $cond2b) {
            persist_snapshots($conn, $batch);
            $cursorseq->set_cursor($page);
            $total_saved += $batch_size;
            Logger::info("Saved $batch_size jobs ($total_saved total, page $page).");
            break;
        }
        $page++;
    }

    if ($total_saved == 0) {
        Logger::info("Nothing to save. Skipping db insertion.");
    }
}

