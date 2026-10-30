<?php
require 'vendor/autoload.php';

use Utils\Crawler\CasperTrio;

function get_total_job_offers($base_url){
    $target_site = $base_url. '/#ofertaEmpleo';

    $casper = new CasperTrio();

    // Forward options to PhantomJS: ignore SSL errors
    $casper->setOptions(array(
                              'ignore-ssl-errors' => 'yes'
                              ));
    $casper->start($target_site);

    // Wait for '.itemEmpleo' but fetch '.dgrid-status',
    // otherise problems arise.
    // '.dgrid-navigation' is at bottom right
    $casper->waitForSelector('.itemEmpleo', 30000);
    // '.dgrid-status' is at bottom left
    $casper->fetchText('.dgrid-status');

    putenv('OPENSSL_CONF=/dev/null');  // fix phpcasperjs bug
    $casper->run();

    $text = ($casper->getOutput())[15];
    $casper = null;

    return trim(explode('de',explode('resultados', $text)[0])[1]);
}
