<?php
require 'vendor/autoload.php';
require 'src/scripts/indexer/helpers.php';
require 'src/utils/indexing.php';

use Utils\Connectivity\Database;
use Utils\DatabaseOps\CursorSeq;

function main(){
    $api_endpoint = "https://simo.cnsc.gov.co";
    $dbname = 'simo';
    try {
        $conn = Database::admin($dbname);
        indexer($conn, $api_endpoint);
    } catch (PDOException $e) {
        echo "Error: ". $e->getMessage(). PHP_EOL;
    } finally {
        $conn = null;
    }

}

function indexer($conn, $api_endpoint){

    // Batch settings
    $batch = array();
    $batch_size = 0;
    $batch_size_limit = 5; // 200

    $min_page = 1;
    $max_page = 10; // test

    // get_max_page() is not smart. Conflicting cases:
    // When called at start of script, get_max_page() returns 100.
    // During execution of the script max page change.
    // $max_page = get_max_page($path2casper, $api_endpoint);

    $cursor_key = 'simo_indexer_cursorseq';
    $cursorseq = new CursorSeq($conn, $cursor_key);
    $page = $cursorseq->get_cursor($min_page);

    // Prepare API request
    $jobs_per_page = 3; // 50
    $base_url = $api_endpoint. '/empleos/ofertaPublica/?size='. $jobs_per_page;

    $start_time = time();
    $timeout = 30; // seconds
    while(true){
        $new_jobs = get_api_data($base_url, $page); # fetch jobs for a given page

        if (count($new_jobs) > 0) {
            [$batch, $pkeys, $added_jobs_n] = add_new_jobs($batch, $pkeys, $new_jobs);
            $batch_size = $batch_size + $added_jobs_n;
        }
        $cond1 = $batch_size > $batch_size_limit;
        $cond2a = $batch_size > 0;
        $cond2b = time() - $start_time > $timeout;
        if ($cond1 || ($cond2a && $cond2b)) {
            persist_snapshots($conn, $batch);
            $cursorseq->set_cursor($page);
            $batch_size = 0;
        } else {
            echo date('Y-m-d H:i:s') . " - Nothing to save. Skipping db insertion.\n";
        }

        // go to next page or page 1
        $page = ($page > $max_page)? 1 : $page++;
    }
}

