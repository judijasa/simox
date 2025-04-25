<?php
// vendor/autoload: stackoverflow.com/questions/41209349/requirevendor-autoload-php-failed-to-open-stream
require 'vendor/autoload.php';
require 'src/scripts/simo_indexer/functions.php';
require 'src/utils/connectivity.php';
require 'src/utils/db_ops.php';
require 'src/utils/indexing.php';
require_once 'src/utils/CasperTrio.php'; // Replaces `use Browser\Casper;`

define('MAX_FILE_SIZE', 4000000);
use Sunra\PhpSimple\HtmlDomParser;

// The PHP Object Casper is defined in:
// vendor/phpcasperjs/phpcasperjs/src/Casper.php
//use Browser\Casper; // replaced by `require_once 'src/utils/CasperTrio.php';`

// Starting clock time in seconds
//$start_time = microtime(true);

function indexer($mod=0, $div=1){
    assert(gettype($mod) == 'integer', 'mod must be of integer type');
    assert(gettype($div) == 'integer', 'div must be of integer type');
    assert($mod >= 0, 'mod must be a positive or zero integer');
    assert($div > 0, 'div must be a positive integer');
    assert($mod < $div, 'mod must be strictly lesser than div');

    // INI format similar to SH
    $cnf = parse_ini_file("src/config.sh");
    $path2casper = $cnf["PATH2CASPER"];
    $target_site = $cnf["SITE"];  # Uncomment after test
    #$target_site = 'https://simo.cnsc.gov.co/#homeCiudadano';  # remove after site2 test
    $dbname = 'simo';
    /* // Remove this once you test the db query approach
    $departamentos = ['Amazonas', 'Antioquia', 'Arauca', 'Archipiélago de San Andrés, Providencia y Santa Catalina',
                  'Atlántico', 'Bogotá D.C.', 'Bolívar', 'Boyacá', 'Caldas', 'Caquetá', 'Casanare', 'Cauca', 'Cesar',
                  'Chocó', 'Córdoba', 'Cundinamarca', 'Guainía', 'Guaviare', 'Huila', 'La Guajira', 'Magdalena',
                  'Meta', 'Nariño', 'Norte de Santander', 'Quindío', 'Risaralda', 'Santander', 'Sucre', 'Tolima',
                  'Valle del Cauca', 'Vaupés', 'Vichada', 'Putumayo', 'No_Aplica'];
    */
    try {
        $conn = new adminPDO($dbname);
        $query = "SELECT nombre FROM dpto_colombia";
        $stmt = $conn->query($query);
        $departamentos = $stmt->fetchAll(PDO::FETCH_COLUMN); // TODO: Test
        $cursor = get_cursor($conn, 'simo_website_cursor', $mod, $div);
        $cursor = $cursor? $cursor : cantorPair(1, 0); // if (cursor is set) then (use cursor) else (set first dpto and first page)
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
    #$casper = new Casper($path2casper);
    $casper = new CasperTrio($path2casper);

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

    $res = cantorUnpair($cursor);
    $departamento_cursor = $res[0]; // resume from last dept in case not finished yet
    $last_page_loaded = $res[0];
    $departamentos_count = count($departamentos);

    $start_time = time();  // Record the start time
    //$timeout = 45 * 60 // 45 min (runs hourly)
    $timeout = 30; // remove after test
    $is_timeout = false;
    $is_first_loop = true;
    while(true){ # break by timeout
        $total_job_offers_n = get_total_job_offers_from_home_ciudadano($path2casper, $target_site, $departamento_cursor);
        $total_pages = TotalPages_from_TotalJobOffers($total_job_offers_n);
        //if($total_job_offers_n !== null and $total_job_offers_n > 0){
        if($total_job_offers_n === 0){
            // TODO: Make a dpto_colombia_seq public.sequence (good practice)
            $departamento_cursor = ($departamento_cursor === $departamentos_count)? 1 : $departamento_cursor++;
            $cursor = cantorPair($departamento_cursor, 0); // jump to next dpto and set page to 0
            try {
                $conn = new adminPDO($dbname);
                set_cursor($conn, 'simo_website_cursor', $cursor, $mod, $div);
            } catch (PDOException $e) {
                echo "Error: ". $e->getMessage(). PHP_EOL;
            } finally {
                $conn = null;
            }
            continue; // restart loop
        }
        // BEGIN Approach that starts from https://simo.cnsc.gov.co/#homeCiudadano
        // Note: Discarded because it is not redirecting to page with jobs filtered by 'departamento'
        // Re-trying this approach because it provides a way to access non-territorial job offers ('No_Aplica')
        // Step 1: Open tab with list of 'departamentos'
        // $sel = '#showButtonDepartamento';
        // $casper->waitForSelector($sel, 30000);  // wait for target button to show up
        // $casper->click($sel);

        // $sel = sprintf('.enlaceOver[data-sigeca-click-oferta-empleo=\"%s\"]', $departamento_cursor);
        // In my last try, not even my manual click in the browser worked to redirect to new page ...?
        // $sel below is an alternative tag (same result, though)
        // $sel = sprintf('i.fuenteCNSC.material-icons.mr-5.va-s.azulAccesible2.desplegar.puntero[data-sigeca-click-oferta-empleo=\"%s\"]', $departamento_cursor);
        // sometimes the selector is found but click doesn't work. this happens because
        // you are in the wrong site, even if for some reason the crawler finds the selector.
        // $casper->waitForSelector($sel, 30000);  // wait for target button to show up
        // $casper->click($sel);
        // END Approach that starts from https://simo.cnsc.gov.co/#homeCiudadano

        // BEGIN Approach that starts from https://simo.cnsc.gov.co/#ofertaEmpleo
        $sel = 'input#dijit_form_FilteringSelect_1.dijitReset.dijitInputInner';
        $casper->waitForSelector($sel, 30000);  // wait for target button to show up
        $casper->click($sel);
        $casper->sendKeys($sel, (string) $departamentos[$departamento_cursor - 1], $reset=true); // type new target page
        $sel ='span.b_buscarEmpleo.botonMediano.amarillo';
        $casper->waitForSelector($sel, 30000);  // wait for 'Buscar' button
        $casper->click($sel);
        // END Approach that starts from https://simo.cnsc.gov.co/#ofertaEmpleo

        //***************************
        //******** BEGIN TEST *******
        // Use fetchText() so that getOutput don't return the whole HTML...
        // $casper->fetchText('.dgrid-status'); // test: current page
        //$casper->run(); // test: current page
        //print_r(array_slice($casper->getOutput(), 0, 41, true)); // test: current page
        // var_dump($casper->getOutput()); // test: current page
        //exit(); // test: current page
        //******** END TEST *********
        //***************************

        /*
        TODO:

          1. (DONE) List all Departamentos as shown in "Busqueda por Departamentos", including No_Aplica.
          2. (DONE, pending test) Open tab with "Busqueda por Departamentos".
          3. Create While loop that scans all Deparatamentos and No_Aplica.
          4. For a cycle, under a specific Departamento, click on the button with that Departamento.
          5. Proceed with the exsiting script to scan all the job offers of that Departamento.
          6. After crawling the last job offer of that Departamento, go back to item 2.
        */

        $total_pages_per_thread = ceil($total_pages / $div); // scan all pages after each exec
        $pages_per_load = 5;
        // $loads_per_mod = ceil($total_pages_per_thread / $pages_per_load); // not necessary
        // $total_pages_per_thread = $pages_per_load * $loads_per_mod; // in case $loads_per_mod is hard typed
        //
        // There are two approaches for concurrency: divide threads
        // by module arithmetics or by consecutive blocks/batches.
        // We use the latter because it is easier to crawl as we
        // can jump to the next page while in the same thread.
        // TODO: Think how to keep the cursor for concurrency > 1.
        // One cursor or one per thread? If only one cursor,
        // from which thread should come from? From the
        // min cursor among all threads? How do you know which
        // is min if the scan is cyclical as it is? If one cursor
        // per thread, how to reassign batches in the next
        // execution? If you take the min cursor among all threads,
        // you may overlap with previous executions if one thread
        // makes 2 batches and the other makes only 1.
        // WARNING: Biggest challenge of concurrence, how to keep
        // cursor with two orders: departamento_cursor and page? Consider
        // handling departamento_cursor subcursor with mod arithmetic?
        // Complicated because it forces different threads
        // to scan different dptos, which can lead to inhomogenous
        // load.
        // SOLUTION: Use mod arithmetic for concurrency and one
        // cursor per thread.  All you need to do is to replace jump
        // to next page to jump to specific page.
        if($is_first_loop){
            $delta_pages = ($last_page_loaded === 0)? $total_pages_per_thread * $mod : 0;
            $page = ($last_page_loaded + 1) + $delta_pages;
            $is_first_loop = false;
        }else{
            $page = 1;
        }
        $arrObj_chunk = new ArrayObject();

        // Go to page...
        if($page > 1 and $page <= $total_pages){

            $sel = 'input.dgrid-page-input';
            $casper->waitForSelector($sel, 30000); // wait for page field selector
            $casper->sendKeys($sel, (string) $page, $reset=true); // type new target page
            $sel = 'span.dgrid-next.dgrid-page-link';
            $casper->waitForSelector($sel, 30000); // wait for next page button
            $casper->click($sel); // click next page button to go to target page

            // BEGIN TEST
            //$casper->waitForSelector('.dgrid-status',30000); // test: current page
            //$casper->fetchText('.dgrid-status'); // test: current page
            $casper->run(); // test: current page
            print_r(array_slice($casper->getOutput(), 0, 46, true)); // test: current page
            exit(); // test: current page
            // END TEST
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
        //print_r(array_slice($casper->getOutput(), 22, 20,true));
        // Mismatch identified in clientutils.js.  See DEBUG notes.
        //exit;
        //********************************

        // fetch data...
        // github.com/synackSA/casperjs-php
        // github.com/alwex/php-casperjs/blob/master/src/Casper.php
        // Code here to fetch data if you want
        $counter = 1;
        while ($counter <= $total_pages_per_thread) {
            if ($page > $total_pages){
                /* // go to mod's min page
                $page = 1 + $pages_per_load * $mod;
                $casper->waitForSelector('input.dgrid-page-input',30000); // wait for page field selector
                $casper->sendKeys('input.dgrid-page-input', (string) $page, $reset=true); // type new target page
                // Even at the last page, there is a next page button...
                $casper->waitForSelector('span.dgrid-next.dgrid-page-link',30000); // wait for next page button
                $casper->click('span.dgrid-next.dgrid-page-link'); // click next page button to go to target page
                */
                $departamento_cursor = ($departamento_cursor === $departamentos_count)? 1 : $departamento_cursor++;
                $cursor = cantorPair($departamento_cursor, 0); // jump to next dpto and set page to 0
                try {
                    $conn = new adminPDO($dbname);
                    set_cursor($conn, 'simo_website_cursor', $cursor, $mod, $div);
                } catch (PDOException $e) {
                    echo "Error: ". $e->getMessage(). PHP_EOL;
                } finally {
                    $conn = null;
                }
                break;
            }

            // Wait for 3 secs:
            //$casper->wait(3000);

            //$casper->fetchText('input.dgrid-page-input'); // test
            //$casper->fetchText('.dgrid-status'); // test

            // Wait for Selector (max 30 secs):
            $casper->waitForSelector('.itemEmpleo', 30000);

            //$casper->fetchText('input.dgrid-page-input'); // test
            //$casper->fetchText('.dgrid-status'); // test

            // Open Jobs Details...
            $casper->click('i.fuenteCNSC.material-icons.t-24.puntero.expandir_mas');
            $casper->waitForSelector('.detalleEmpleo', 30000);

            //*****************************
            // DEBUGGING...
            //*****************************
            //if($page == 1){
            //Display 15 steps starting from step 10:
            //$casper->run();  # execute casper commands stated above
            //print_r(array_slice($casper->getOutput(), 10, 15,true));
            //print_r(array_slice($casper->getOutput(), 25, 30,true));
            //}
            //exit;
            //*****************************
            $casper->run();  # execute casper commands stated above

            // getHtml([Selector]):
            // webdriver.io/docs/api/element/getHTML/

            $html = $casper->getHtml();
            $dom = HtmlDomParser::str_get_html($html);

            //***************************************
            // 2save $html in local folder
            //***************************************
            // __DIR__ = current directory (best practices)
            // To exec this file while using file_put_contents() use:
            // $ sudo php index.php
            // TODO: Find out why no $html is found
            //file_put_contents(__DIR__. '/tmp/simo-page-2.html', $html ); // test
            //exit(); // test
            //$html = file_get_contents('tmp/simo-page-2.html');
            //$dom = HtmlDomParser::str_get_html( $html );
            //***************************************

            // Capture current page:
            // As object, otherwise memory overaload
            $curr_pg = new ArrayObject(array());
            // I AM HERE: Line below shows error because $dom returns false.
            $curr_pg = $dom->find('input.dgrid-page-input', 0)->value;

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
            // TODO: Make sure $arrObj_elems1 and $arrObj_elems2 have something to process otherwise break or continue
            // Merge data...
            $arrObj_elems = prepare_and_merge($arrObj_elems1, $arrObj_elems2);
            // Remove unwanted items (and add page item)...
            $arrObj_elems = post_process_1($arrObj_elems, $curr_pg);
            // TODO: Fix. According to print_r below, you are not filtering by dpto (expecting 'Amazonas')
            //print_r($arrObj_elems[0]); // test
            //echo "<br><br>"; // test
            //print_r($arrObj_elems[8]); // test

            //$arr = (array)$arrObj_elems; old version
            //if ($arr) { // old version
            // If $arrObj_elems is not empty...
            $arr = $arrObj_elems->getArrayCopy();
            if ($arr) {
                $arrObj_chunk->append($arrObj_elems);
            }

            $arr = $arrObj_chunk->getArrayCopy(); // do you really need this line?
            # Save progress after batch load is complete...
            //if (($counter % $pages_per_load === 0 or $page === $total_pages) and !empty($arr)){
            if(true){
                try{
                    $conn = new adminPDO($dbname);
                    foreach($arrObj_chunk as $arrObj_elems){
                        foreach($arrObj_elems as $arrObj_items){
                            //$pagina = ((array) $arrObj_items)[0]; // test
                            //$pagina = trim(explode(':', $pagina)[1]); // test
                            insert2db($conn, $arrObj_items, $departamento_cursor);
                        }
                    }
                    $cursor = cantorPair($departamento_cursor, $page);
                    set_cursor($conn, 'simo_website_cursor', $cursor, $mod, $div);
                } catch (PDOException $e) {
                    echo "Error: " . $e->getMessage();
                } finally {
                    $conn = null;
                }
                $arrObj_chunk = new ArrayObject(array());
                $total_job_offers_n = get_total_job_offers_from_home_ciudadano($path2casper, $target_site, $departamento_cursor);
                $total_pages = TotalPages_from_TotalJobOffers($total_job_offers_n);
            } else {
                echo date('Y-m-d H:i:s') . " - Nothing to save. Skip db insertion (this shouldn't be happening, revise pre-emptive checks)...\n";
            }# else ... here do something if $arr is empty, perhaps jump to next dpt

            $dom->clear();
            unset($dom);

            if (time() - $start_time > $timeout) {
                echo date('Y-m-d H:i:s') . " - Timeout exit.\n";
                $is_timeout = true;
                break;
            }
            // go to next page or to page 1
            // CAREFUL: statements of the typ `$casper->...` are only exec after $casper->run(),
            // but there's no such line after the statements below.
            $page = $page + $div;
            if($page < $total_pages){
                //$casper->waitForSelector('span.dgrid-next.dgrid-page-link',30000); // wait for next page button selector
                // $casper->click('span.dgrid-next.dgrid-page-link'); // go to next page
                // Go to next mod page:
                $casper->waitForSelector('input.dgrid-page-input',30000); // wait for page field selector
                $casper->sendKeys('input.dgrid-page-input', (string) $page, $reset=true); // type new target page
                $casper->waitForSelector('span.dgrid-next.dgrid-page-link',30000); // wait for next page button
                $casper->click('span.dgrid-next.dgrid-page-link'); // click next page button to go to target page
            }
            $counter++;
            // set a break every n chunks processed (resumes in 1 hour using cron)
        } //while: scan pages
        if($is_timeout){
            break;
        }
        $departamento_cursor = ($departamento_cursor === $departamentos_count)? 1 : $departamento_cursor++;
    } // while: scan dptos
    //$end_time = microtime(true);
    //$exec_time = $end_time - $start_time;
    //$exec_time = gmdate("H:i:s",$exec_time);
    //echo $exec_time;
}
?>
