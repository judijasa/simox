<?php
    // Starting clock time in seconds
    //$start_time = microtime(true);
    
    // INI format similar to SH
    $cnf = parse_ini_file("admin_config.sh");
    $path2casper = $cnf["PATH2CASPER"];
    $target_site = $cnf["SITE"];
    
    // new connection
    $servername = $cnf["SERVER"];
    $username = $cnf["USER"];
    $password = $cnf["PASSWORD"];
    $dbname = $cnf["DATABASE"];
    
    $conn = new PDO("mysql:host=$servername;port=3306;dbname=$dbname", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    /*
     
     NOTE: When in trouble with (...)->find() use php string manipulation alternatives.
     
     Building Your Own Web Scraper with CasperJS (not our case)
     dzone.com/articles/building-your-own-web-scraper-in-nodejs
     
     OPEN WITH BROWSER
     localhost/web-projects/scraping_SIMO/filename.php
     OR WITH PHP (TERMINAL): php index.php
     
     CASPER-PHP (WITH EXAMPLES)
     github.com/alwex/php-casperjs/issues/25
     github.com/synackSA/casperjs-php
     
     docs.casperjs.org/en/latest/quickstart.html#a-minimal-scraping-script
     (Only for casperjs, not for phpcasperjs)
     Please take a look at the documentation for
     'then' method as it waits for earlier steps
     to be finished before running the next step. This
     is important to make sure your asserts work fine.
     
     GENERAL
     www.alanmbarr.com/blog/scrape-dynamic-sites-phantomjs-php/
     
     ACRÓNIMOS INSTITUCIONALES
     SIMO: Sistema de Apoyo para la Igualdad, el Mérito y la Oportunidad
     VRM: Verificación de Requisitos Mínimos
     
     WEBSITES TO SCRAP
     simo-ppal.cnsc.gov.co/#ofertaEmpleo
     www.cnsc.gov.co/
     [universidades]
     
     INFO ON SIMO WEB ARCHITECTURE
     simo.cnsc.gov.co/cnscwiki/doku.php?id=ea:tobe:aplicaciones:sigeca:arquitectura
     
     ISSUES:
     Scraping a gridvew with paging
     www.codeproject.com/Questions/716286/Screenscraping-a-gridview-with-paging
  
     SIMPLE_HTML_DOM GET DYNAMIC CONTENT
     stackoverflow.com/questions/39921426/simple-html-dom-get-dynamic-content-loaded-with-js */
    
    // vendor/autoload: stackoverflow.com/questions/41209349/requirevendor-autoload-php-failed-to-open-stream
    
    require '../vendor/autoload.php';
    require 'functions.php';
    
    // MAX_FILE_SIZE: stackoverflow.com/questions/48098911/the-use-of-the-php-simple-html-dom-parser-when-parsing-large-html-files-result
    //  stackoverflow.com/questions/30966569/str-get-html-doesnt-work-and-return-blank/30967650
    
    // 'use' search for Classes using namespace
    // e.g. Browser or Sunra.
    // The search is restricted to
    // dependencies listed in Composer.json
    
    define('MAX_FILE_SIZE', 4000000);
    use Sunra\PhpSimple\HtmlDomParser;
    
    // The PHP Object Casper is defined in:
    // vendor/phpcasperjs/phpcasperjs/src/Casper.php
    use Browser\Casper;
    
    // Casper.php is a wrapper of Casper.js
    // Instantiate the PHP Object Casper
    //  with my location of Casper.js
    
    // Default path 
    #$casper = new Casper();
    // Custom path
    $casper = new Casper($path2casper);
    
    // Forward options to phantomJS
    // for example to ignore ssl errors
    $casper->setOptions(array(
                              'ignore-ssl-errors' => 'yes'
                              ));

    // Old Name: 'simo.cnsc.gov.co/#ofertaEmpleo'
    $casper->start($target_site);
    
    //*******************************************
    // No need to close popup window; discard the following commands...
    //$casper->waitForSelector('span.dijitDialogCloseIcon',3000);
    //$casper->click('span.dijitDialogCloseIcon'); // close popup window
    //*******************************************
    
    // Catch ext args: myfile.php -- arg (not myfile.php --arg)
    // [0]=>'myfile.php' [1]=>'--' [2]=>'arg'
    parse_str($argv[2],$page_number);
    $end_pg = $page_number['end'];
    $last_pg_loaded = $page_number['last'];
      
    // if table does not exist...
    $init_pg = $last_pg_loaded + 1;
    
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
    if($init_pg > 1){
        $casper->waitForSelector('input.dgrid-page-input',30000);
        // sendKeysReset includes Enter keypress
        $casper->sendKeysReset('input.dgrid-page-input', $init_pg);
        // Don't click with command below, it will send you to pg 1 again.
        //$casper->click('span.b_buscarEmpleo.botonGrande');
        
        //$casper->waitForSelector('input.dgrid-page-input',30000); // test
        //$casper->fetchText('input.dgrid-page-input'); // test
        //$casper->fetchText('.dgrid-status'); // test
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
    
    $pg = $init_pg;
    while ($pg <= $end_pg) {
            
        // Wait for 3 secs:
        //$casper->wait(3000);
        
        if($pg > $init_pg){
            // Go to next page...
            $casper->waitForSelector('span.dgrid-next.dgrid-page-link',30000);
            $casper->click('span.dgrid-next.dgrid-page-link');
        }

        //$casper->fetchText('input.dgrid-page-input'); // test
        //$casper->fetchText('.dgrid-status'); // test
        
        // Wait for Selector (max 30 secs):
        $casper->waitForSelector('.itemEmpleo',30000);

        //$casper->fetchText('input.dgrid-page-input'); // test        
        //$casper->fetchText('.dgrid-status'); // test        

        // Open Jobs Details...
        $casper->click('i.fuenteCNSC.material-icons.t-24.puntero.expandir_mas');
        $casper->waitForSelector('.detalleEmpleo',30000);
                
        $casper->run();
  
        //*****************************
        // DEBUGGING...
        //*****************************
        //if($pg == 1){
        //Display 15 steps starting from step 10:
        //print_r(array_slice($casper->getOutput(), 10, 15,true));
        //print_r(array_slice($casper->getOutput(), 25, 30,true));
        //}
        //exit;
        //*****************************
        
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
        // Check if $curr_pg matches par $pg:
        //************************************
        //echo "<br>Current page = ". $curr_pg. ", Loop param = ". $pg. ". <br><br>";
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
        
        // If $arrObj_elems is not empty...
        $arr = (array)$arrObj_elems;
        if ($arr) {
            // Send to Database...
            foreach($arrObj_elems as $arrObj_items){
                insert2db($arrObj_items, $conn);
            } // foreach
        }
        $dom->clear();
        unset($dom);
    
        $pg = $pg + 1;
    } //while
    $conn = null;
    //$end_time = microtime(true);
    //$exec_time = $end_time - $start_time;
    //$exec_time = gmdate("H:i:s",$exec_time);
    //echo $exec_time;
?>
