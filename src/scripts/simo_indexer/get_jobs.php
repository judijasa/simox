<?php
// On 2024-01-18, simo website changed significatively.
// In response to this change, we create a new version
// of this file and keep this old version for future
// reference.
//
//
// vendor/autoload: stackoverflow.com/questions/41209349/requirevendor-autoload-php-failed-to-open-stream
require 'vendor/autoload.php';
require 'src/scripts/simo_indexer/functions.php';
require 'src/utils/connectivity.php';
require 'src/utils/db_ops.php';

define('MAX_FILE_SIZE', 4000000);
use Sunra\PhpSimple\HtmlDomParser;

// The PHP Object Casper is defined in:
// vendor/phpcasperjs/phpcasperjs/src/Casper.php
use Browser\Casper;

// Starting clock time in seconds
//$start_time = microtime(true);

// INI format similar to SH
$cnf = parse_ini_file("src/config.sh");
$path2casper = $cnf["PATH2CASPER"];
$target_site = $cnf["SITE"];

$dbname = 'simo';
try {
    $conn = new readerPDO($dbname);
    $last_page_loaded = get_cursor($conn, 'simo_website_page');
} catch (PDOException $e) {
    echo "Error: ". $e->getMessage(). PHP_EOL;
} finally {
    $conn = null;
}

// MAX_FILE_SIZE: stackoverflow.com/questions/48098911/the-use-of-the-php-simple-html-dom-parser-when-parsing-large-html-files-result
//  stackoverflow.com/questions/30966569/str-get-html-doesnt-work-and-return-blank/30967650

// 'use' search for Classes using namespace
// e.g. Browser or Sunra.
// The search is restricted to
// dependencies listed in Composer.json


// Casper.php is a wrapper of Casper.js
// Instantiate the PHP Object Casper
//  with my location of Casper.js

#$casper = new Casper(); # default path
$casper = new Casper($path2casper);

// Forward options to phantomJS
// for example to ignore ssl errors
$casper->setOptions(array(
                          'ignore-ssl-errors' => 'yes'
                          ));

// Old Name: 'simo.cnsc.gov.co/#ofertaEmpleo'
$casper->start($target_site);
#$casper->waitForSelector('.itemEmpleo',30000); # test
#$casper->run(); # test
#var_dump($casper->getOutput()); #test
#exit(); # test
//*******************************************
// No need to close popup window; discard the following commands...
//$casper->waitForSelector('span.dijitDialogCloseIcon',3000);
//$casper->click('span.dijitDialogCloseIcon'); // close popup window
//*******************************************

// Catch ext args: myfile.php -- arg (not myfile.php --arg)
// [0]=>'myfile.php' [1]=>'--' [2]=>'arg'
// parse_str($argv[2],$page_number); // old
// $end_pg = $page_number['end']; // old
// $last_pg_loaded = $page_number['last']; // old

// new method: stop if (pg == null), insted of stop at if (pg == pg_end)

// How to set a value of an input tag in casperJS:
// stackoverflow.com/questions/18172040/how-to-set-value-of-an-input-tag-in-casperjs
// sendKeys() in php-casperjs (our case):
// github.com/alwex/php-casperjs/issues/50
// Example of fillForm() (not our case):
// github.com/synackSA/casperjs-php
// In example above (webaddress): 'form' is the tag
// 'action' is an attribute of the tag 'form'
// '/search' is a value of the attribute 'action' (e.g. next site)
// There is an 'input' tag within 'form'
// whose 'name' is 'q'. It has an attribute called
// 'value' whose value is 'search'.

// Go to page...
$page = $last_page_loaded + 1;
$pages_per_load = 3;
$pages_per_hour = $pages_per_load*2;
$arrObj_batch = new ArrayObject();

$total_job_offers = get_total_job_offers($path2casper, $target_site);
$total_pages = TotalPages_from_TotalJobOffers($total_job_offers);

if($page > 1 AND $page <= $total_pages){
    $casper->waitForSelector('input.dgrid-page-input',30000); // wait for page field selector
    $casper->sendKeys('input.dgrid-page-input', (string) $page, $reset=true); // type new target page
    $casper->waitForSelector('span.dgrid-next.dgrid-page-link',30000); // wait for next page button
    $casper->click('span.dgrid-next.dgrid-page-link'); // click next page button to go to target page

    //$casper->waitForSelector('.dgrid-status',30000); // test: current page
    //$casper->fetchText('.dgrid-status'); // test: current page
    //$casper->run(); // test: current page
    //print_r(array_slice($casper->getOutput(), 0, 46,true)); // test: current page
    //exit(); // test: current page
}

//********************************
// DEBUGGING...
//********************************
//$casper->run();
//$casper->getOutput();
//print_r(array_slice($casper->getOutput(), 0, 27,true));
// after running first 27 logs, all but last ([26]) matched between Macbook and Azure
// Now running five logs after [26]...
//print_r(array_slice($casper->getOutput(), 25, 20,true));
// Mismatch identified in clientutils.js.  See DEBUG notes.
//exit;
//********************************

// fetch data...
// github.com/synackSA/casperjs-php
// github.com/alwex/php-casperjs/blob/master/src/Casper.php
// Code here to fetch data if you want
$counter = 1;
while ($counter <= $pages_per_hour) {
    if ($page > $total_pages){ # back to page 1
        $page = 1;
        $casper->waitForSelector('input.dgrid-page-input',30000); // wait for page field selector
        $casper->sendKeys('input.dgrid-page-input', (string) $page, $reset=true); // type new target page
        $casper->waitForSelector('span.dgrid-next.dgrid-page-link',30000); // wait for next page button
        $casper->click('span.dgrid-next.dgrid-page-link'); // click next page button to go to target page
    }

    // Wait for 3 secs:
    //$casper->wait(3000);

    //$casper->fetchText('input.dgrid-page-input'); // test
    //$casper->fetchText('.dgrid-status'); // test

    // Wait for Selector (max 30 secs):
    $casper->waitForSelector('.itemEmpleo',30000);

    //$casper->fetchText('input.dgrid-page-input'); // test
    //$casper->fetchText('.dgrid-status'); // test

    // Open Jobs Details...
    $casper->click('i.fuenteCNSC.material-icons.t-24.puntero.expandir_mas');
    $casper->waitForSelector('.detalleEmpleo',30000);

    //*****************************
    // DEBUGGING...
    //*****************************
    //if($page == 1){
    //Display 15 steps starting from step 10:
    //print_r(array_slice($casper->getOutput(), 10, 15,true));
    //print_r(array_slice($casper->getOutput(), 25, 30,true));
    //}
    //exit;
    //*****************************
    $casper->run();

    // getHtml([Selector]):
    // webdriver.io/docs/api/element/getHTML/

    $html = $casper->getHtml();
    $dom = HtmlDomParser::str_get_html( $html );

    //***************************************
    // 2save $html in local folder
    //***************************************
    // __DIR__ = current directory (best practices)
    // To exec this file while using file_put_contents() use:
    // $ sudo php index.php

    //file_put_contents(__DIR__. '/tmp/simo-page-2.html', $html );
    //$html = file_get_contents('tmp/simo-page-2.html');
    //$dom = HtmlDomParser::str_get_html( $html );
    //***************************************

    // Capture current page:
    // As object, otherwise memory overaload
    $curr_pg = new ArrayObject(array());
    $curr_pg = $dom->find('input.dgrid-page-input',0)->value;

    //************************************
    // Check if $curr_pg matches par $page:
    //************************************
    //echo "<br>Current page = ". $curr_pg. ", Loop param = ". $page. ". <br><br>";
    //************************************

    // Capture Job Profiles...
    $arrObj_elems1 = new ArrayObject(array());
    $arrObj_elems1 = $dom->find('.itemEmpleo');
    // Capture Job Details...
    $arrObj_elems2 = new ArrayObject(array());
    $arrObj_elems2 = $dom->find('.detalleEmpleo');
    // Merge data...
    $arrObj_elems = prepare_and_merge($arrObj_elems1,$arrObj_elems2);
    // Remove unwanted items (and add page item)...
    $arrObj_elems = post_process_1($arrObj_elems,$curr_pg);
    //print_r($arrObj_elems[0]);
    //echo "<br><br>";
    //print_r($arrObj_elems[8]);

    //$arr = (array)$arrObj_elems; old version
    //if ($arr) { // old version
    // If $arrObj_elems is not empty...
    $arr = $arrObj_elems->getArrayCopy();
    if ($arr) {
        $arrObj_batch->append($arrObj_elems);
    }

    $arr = $arrObj_batch->getArrayCopy();
    if (($counter % $pages_per_load === 0 or $page === $total_pages) and !empty($arr)){
        try{
            $conn = new adminPDO($dbname);
            foreach($arrObj_batch as $arrObj_elems){
                foreach($arrObj_elems as $arrObj_items){
                    insert2db($conn, $arrObj_items);
                }
            }
        set_cursor($conn, 'simo_website_page', $page);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        } finally {
            $conn = null;
        }
        $arrObj_batch = new ArrayObject(array());
        $total_job_offers = get_total_job_offers($path2casper, $target_site);
        $total_pages = TotalPages_from_TotalJobOffers($total_job_offers);
    }

    $dom->clear();
    unset($dom);
    // go to next page or to page 1
    // CAREFUL: statements of the typ `$casper->...` are only exec after $casper->run(),
    // but there's no such line after the statements below.
    //$casper->waitForSelector('span.dgrid-next.dgrid-page-link',30000); // wait for next page button selector
    $casper->click('span.dgrid-next.dgrid-page-link'); // go to next page
    $counter++;
    $page++;
    // set a break every n batches processed (resumes in 1 hour using cron)
} //while

//$end_time = microtime(true);
//$exec_time = $end_time - $start_time;
//$exec_time = gmdate("H:i:s",$exec_time);
//echo $exec_time;
?>
