<?php
require 'vendor/autoload.php';
require __DIR__ . '/helper.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Logger;

#[CronJob(schedule: '0 2 * * *', scope: 'worker')]
#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]
function main($conn, $batch_size_limit = 200, $jobs_per_page = 50, $timeout = 60 * 45){
    $base_url = "https://simo.cnsc.gov.co";
    indexer(
        $conn,
        $base_url,
        $batch_size_limit,
        $jobs_per_page,
        $timeout);
}

function indexer($conn, $base_url, $batch_size_limit, $jobs_per_page, $timeout){
    $batch = array();
    $batch_job_ids = array();
    $batch_size = 0;

    // Prepare API request
    $base_api_request = $base_url. '/empleos/ofertaPublica/?size='. $jobs_per_page;
    // Scraped once per run: the website's reported total (PhantomJS navigator).
    // The first empty page is expected at ceil(total / jobs_per_page); the loop
    // ends on the first empty page instead of this bound, since the total can
    // change while the run is in progress.
    $total = (int)get_total_job_offers($base_url);
    $max_page = (int)ceil($total / $jobs_per_page);

    $total_saved = 0;
    $start_time = time();
    $timed_out = false;
    for ($page = 1; ; $page++){
        if (time() - $start_time > $timeout) {
            $timed_out = true;
            break;
        }

        $new_jobs = get_api_data($base_api_request, $page);
        if ($new_jobs instanceof ArrayObject && count($new_jobs) === 0) {
            if ($page != $max_page) {
                Logger::info("WARNING: empty page $page reached not predicted by max page $max_page.");
            }
            break;
        }

        $output = batch_with_new_jobs($batch, $batch_job_ids, $new_jobs);
        [$batch, $batch_job_ids, $added_jobs_n] = $output;
        $batch_size = $batch_size + $added_jobs_n;

        if ($batch_size >= $batch_size_limit) {
            persist_snapshots($conn, $batch);
            $total_saved += $batch_size;
            Logger::info("Saved $batch_size jobs ($total_saved total, page $page).");
            $batch = [];
            $batch_job_ids = [];
            $batch_size = 0;
        }
    }

    // Flush the final partial batch (empty page or timeout).
    if ($batch_size > 0) {
        persist_snapshots($conn, $batch);
        $total_saved += $batch_size;
        Logger::info("Saved $batch_size jobs ($total_saved total, page ".($page - 1).").");
    }

    if ($timed_out) {
        Logger::info("WARNING: timeout stopped crawling at page $page before reaching max page $max_page.");
    }

    if ($total_saved == 0) {
        Logger::info("Nothing to save. Skipping db insertion.");
    }
}
