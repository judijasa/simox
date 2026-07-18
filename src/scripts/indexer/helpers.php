<?php
require 'vendor/autoload.php';

use Utils\Crawler\CasperTrio;
use Sunra\PhpSimple\HtmlDomParser;

function batch_with_new_jobs($batch, $batch_job_ids, $new_jobs){
    $new_batch = $batch;
    $new_batch_job_ids = $batch_job_ids;
    $added_jobs_n = 0;
    foreach($new_jobs as $job){
        $job_id = $job['id'];
        if (!in_array($job_id, $batch_job_ids, true)){
            $new_batch[] = $job;
            $new_batch_job_ids[] = $job_id;
            $added_jobs_n++;
        }
    }
    return [$new_batch, $new_batch_job_ids, $added_jobs_n];
}

function get_total_job_offers($base_url){
    // About MAX_FILE_SIZE:
    // stackoverflow.com/questions/48098911/the-use-of-the-php-simple-html-dom-parser-when-parsing-large-html-files-result
    //  stackoverflow.com/questions/30966569/str-get-html-doesnt-work-and-return-blank/30967650
    define('MAX_FILE_SIZE', 4000000);
    $cnf = parse_ini_file("src/config.sh");
    $path2casper = $cnf["PATH2CASPER"];
    $target_site = $base_url. '/#ofertaEmpleo';


    $casper = new CasperTrio($path2casper);

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

function get_api_data($base_api_request, $page){
    // Example of url (includes search by departamento):
    // https://simo.cnsc.gov.co/empleos/ofertaPublica/?search_departamento=1&page=0&size=10

    $api_request = $base_api_request . '&page=' . "$page";

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $api_request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Do not verify certificates; the target site (simo.cnsc.gov.co)
    // is misconfigured; it returns incomplete certificates.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);

    // Execute cURL session and get the response
    $response = curl_exec($ch);

    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL Error: $error");
    }

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Close cURL session
    curl_close($ch);

    // Check if request was successful
    if ($httpCode >= 400) {
        throw new Exception("HTTP Error: $httpCode");
    }

    // Parse JSON response
    // true: returns assoc array
    // false: returns stdClass
    $data = new ArrayObject(json_decode($response, true));

    // Check for JSON parsing errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Error: " . json_last_error_msg());
    }
    return $data;
}

function persist_snapshots($conn, $jobs){
    $sql = <<<EOD
        INSERT INTO empleo_snapshot (
            opec,
            empleo,
            estado_inscripcion,
            favorito,
            inscripcion_id,
            fecha_inscripcion,
            nivel_nombre,
            `access`
        ) VALUES (
            :opec,
            :empleo,
            :estado_inscripcion,
            :favorito,
            :inscripcion_id,
            :fecha_inscripcion,
            :nivel_nombre,
            :access
        )
        EOD;
    $stmt = $conn->prepare($sql);
    foreach ($jobs as $job) {
        $stmt->execute([
            ':opec'               => $job['id'],
            ':empleo'             => json_encode($job['empleo']),
            ':estado_inscripcion' => $job['estadoInscripcion'],
            // cast boolean to int, otherwise PHP convert false to empty string
            ':favorito'           => (int)$job['favorito'],
            ':inscripcion_id'     => json_encode($job['inscripcionId']),
            ':fecha_inscripcion'  => $job['fechaInscripcion'],
            ':nivel_nombre'       => $job['nivelNombre'],
            ':access'             => json_encode($job['access']),
        ]);
    }
}

