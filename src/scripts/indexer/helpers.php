<?php
require 'vendor/autoload.php'; // in case pdo class in vendor
//use Browser\Casper; // used in get_total_job_offers (old method: editing Casper class)
require_once 'src/utils/CasperTrio.php'; // used in get_total_job_offers (new method: child of Casper class)

function get_total_pages($path2casper, $target_site){
    // TODO: Modify this function, currently fetching total jobs
    $casper = new CasperTrio($path2casper);
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

    $casper->run();

    $text = ($casper->getOutput())[15];
    $casper = null;

    return trim(explode('de',explode('resultados', $text)[0])[1]);
}

function polish($debris_var){
    $arrObj = new ArrayObject(array());
    foreach($debris_var as $d){
        /* clean unwanted strings
         www.w3schools.com/PHP/func_string_str_replace.asp

         stackoverflow.com/questions/14743812/how-to-use-str-replace-to-replace-single-and-double-quotes */

        /* THIS METHOD (php str manipulations) WORKS but currently using (...)->find(), which is more robust.

         $find = array("<p class=\"empleoVaca\">","<p class=\"empleoVaca oculto\">","</p>","<span class=\"empleoVaca\">","<span class=\"empleoCier\">","<span aria-hidden=\"true\">","</span>"); // Check for more hidden tags compling in Terminal
         $replace = array("","","","","","");

         // append(): www.geeksforgeeks.org/arrayobject-append-function-in-php/

         $arrObj->append(trim(str_replace($find,$replace,$d)));
         */

        $arrObj->append(trim($d));
    } // foreach
    return $arrObj;
} // function

/*---------------------------------
 The str data $elems, $var_elems store mutiple jobs
 The str data $e, $e_var store a single job
 The array data $debris, $var_debris store single job items
 The str data $d, $e_item store a single job item
 ----------------------------------*/

function prepare_jobprofile($e_var) {
    // remove comments from the html `<!-- comment -->` -> ``
    foreach($e_var->find('comment') as $i){
        $i->outertext = '';
    }
    // replace <i class='aria-hidden'>...</i> with '|' (to be used as separator)
    foreach($e_var->find('i[aria-hidden]') as $i){
        $i->outertext = '|';
    }
    foreach($e_var->find('a') as $i){
        $i->outertext = '';
    }
    foreach($e_var->find('span') as $i){ // `<span>"HOLA"</span>` -> `"HOLA"`
        $i->outertext = $i->innertext;
    }
    foreach($e_var->find('p') as $i){ // `<p>"HOLA"</p>` -> `"HOLA"`
        $i->outertext = $i->innertext;
    }
    $debris = explode('|',$e_var->innertext);
    // 1st element is empty. Remove it:
    array_shift($debris);
    // Get the first 10 items of job offer profile after index 0:
    $debris = array_slice($debris, 0, 10);
    $arrObj_jobitems = polish($debris);
    return $arrObj_jobitems;
} // function

// Job details show after click
// of the Down Arrow in the job offer profile.
function prepare_jobdetails($arrObj_elems2_var) {
    $arrObj_details = new ArrayObject(array());

    foreach($arrObj_elems2_var as $e){
        foreach($e->find('a') as $i){
            $i->outertext = '';
        }
        foreach($e->find('i[aria-hidden]') as $i){
            $i->outertext = '|';
        }
        foreach($e->find('li') as $i){
            $i->outertext = $i->innertext;
        }
        // Removing sections: Propósito, Funciones
        // Desired sections (Estudio, Dependencia, Municipio) are enclosed by <ul class="sinVignetas">...</ul>
        $e = implode(" ",$e->find('ul.sinVignetas'));
        //echo gettype($e);
        /* implode() converts array-object type (input) to string type (output), preventing the call of find() afterwards...*/
        $find = array("<ul class=\"sinVignetas\">", "</ul>", "<span class=\"requLabel\">", "</span>");
        $replace = array("", "", "", "", "", "");
        $e = trim(str_replace($find, $replace, $e));
        $e = explode('|',$e);
        array_shift($e); // removes unwanted first component

        $arrObj_details->append($e);
    }
    return $arrObj_details;
}

function prepare_and_merge($arrObj_elems1_var,$arrObj_elems2_var){
    // Filter outdated job profiles and save their positions
    // to later filter job offer details...
    $current_date = "0000-01-01"; // default: date("Y-m-d");
    $arrObj_profiles = new ArrayObject(array());
    $arrObj_selection = new ArrayObject(array());
    $max = count($arrObj_elems1_var) - 1;

    foreach(range(0, $max) as $i){
        $arrObj_e = prepare_jobprofile($arrObj_elems1_var[$i]);
        // $deadline = Fecha cierre de inscripcion
        // Assuming deadline is in $arrObj_e[7]
        $deadline = trim(explode(':',$arrObj_e[7])[1]);
        if($deadline > $current_date){
            $arrObj_profiles->append($arrObj_e);
            $arrObj_selection->append($i);
        } // if
    } // foreach

    $arrObj_details = prepare_jobdetails($arrObj_elems2_var);

    // Filter job offer details and merge with job offer Profile...
    $n = 0;
    $arrObj_elems = new ArrayObject(array());
    foreach($arrObj_selection as $i){
        // Merge array objects: stackoverflow.com/questions/455700/what-is-the-best-method-to-merge-two-php-objects
        $merged = (object) array_merge((array) $arrObj_profiles[$n], (array) $arrObj_details[$i]);
        $arrObj_elems->append($merged);
        $n = $n + 1;
    }
    return $arrObj_elems;
}

function post_process_1($arrObj_elems_var, $curr_pg_var) {
    // Some general cleaning...
    // step 1: Remove 'Alternativa de estudio' y 'Alternativa de experiencia'
    $arrObj_elems_new = new ArrayObject(array());
    foreach($arrObj_elems_var as $arrObj_items_old){
        $arrObj_items_new = new ArrayObject(array());
        foreach($arrObj_items_old as $item){
            $a = trim(explode(':',$item,2)[0]);
            $b = "Alternativa de estudio";
            $c = "Equivalencia de estudio";
            $d = "Alternativa de experiencia";
            $e = "Equivalencia de experiencia";
            if(($a !== $b)&&($a !== $c)&&($a !== $d)&&($a !== $e)){
                $arrObj_items_new->append($item);
            }
        }
        $arrObj_elems_new->append($arrObj_items_new);
    }
    // step 2: More general cleaning...
    $arrObj_elems_new2 = new ArrayObject(array());
    foreach($arrObj_elems_new as $arrObj_items_new){
        $arrObj_select = new ArrayObject(array());
        $arrObj_dependencia = new ArrayObject(array());
        $arrObj_municipio = new ArrayObject(array());
        $arrObj_vacantes = new ArrayObject(array());
        $max = count($arrObj_items_new) - 1;
        foreach(range(0,$max) as $i){
            $current_item = $arrObj_items_new[$i];
            $a = trim(explode(':',$current_item)[0]);
            $b = "Dependencia";
            $c = "Municipio";
            if(($a == $b)||($a == $c)){
                $arrObj_select->append($i);
                if($a == $b){
                    // It also removes comma in 'dependencia' item
                    $aux = explode(":",$current_item)[1];
                    $aux = trim(str_replace(",","",$aux));
                    $arrObj_dependencia->append($aux);
                } elseif($a == $c) {
                    $aux = explode(":",$current_item)[1];
                    $aux = trim(str_replace(", Total vacantes","",$aux));
                    $arrObj_municipio->append($aux);
                    $aux = explode(":",$current_item)[2];
                    $arrObj_vacantes->append($aux);
                }
            }
        }
        $arrObj_items_new2 = $arrObj_items_new;
        //www.geeksforgeeks.org/removing-array-element-and-re-indexing-in-php/
        //echo 'arrObj_select = ';
        //var_dump($arrObj_select);
        //echo 'BEFORE: arrObj_items_new2[] = ';
        //var_dump($arrObj_items_new2);
        foreach($arrObj_select as $i){
            unset($arrObj_items_new2[$i]); // removing...
        }
        //echo 'AFTER: arrObj_items_new2[] = ';
        //var_dump($arrObj_items_new2);
        // re-indexing...
        $arrObj_items_new2 = (object) array_values((array) $arrObj_items_new);
        // Remove duplicated dependencies and municipalities...
        $aux = array_unique((array) $arrObj_dependencia); // remove duplicates
        //$aux = (array) $arrObj_dependencia; // keeping duplicates
        $dep = (object) ("Dependencia: ". implode(", ",$aux));
        $aux = array_unique((array) $arrObj_municipio); // remove duplicates
        //$aux = (array) $arrObj_municipio; // keeping duplicates
        $mun = (object) ("Municipio: ". implode(", ",$aux));
        //$aux = array_unique((array) $arrObj_vacantes); // remove duplicates
        $aux = (array) $arrObj_vacantes; // keeping duplicates
        //$vac = (object) ("Vacantes: ". implode(", ",$aux));
        $vac = (object) ("Vacantes: ". array_sum($aux));
        // Prepend labels "Dependencia: ", "Municipio: ", "Vacantes: ", respectively.
        $dep = array_values((array) $dep);
        $mun = array_values((array) $mun);
        $vac = array_values((array) $vac);
        //$arrObj_items->append($dep); // not working (?)
        //$arrObj_items->append($mun); // not working (?)
        $arrObj_items_new2 = (object) array_merge((array) $arrObj_items_new2,(array) $dep);
        $arrObj_items_new2 = (object) array_merge((array) $arrObj_items_new2,(array) $mun);
        $arrObj_items_new2 = (object) array_merge((array) $arrObj_items_new2,(array) $vac);
        //} //if
        // Add 'page' item:
        $arrObj_items_new2 = (object) array_merge((array) ("Página: ". $curr_pg_var),(array) $arrObj_items_new2);
        $arrObj_elems_new2->append($arrObj_items_new2);
    } //foreach
    return $arrObj_elems_new2;
}

//**************************************************
//**************************************************

// Note that most SQL queries are in this PHP file.
// Pros: 1) Easier debugging
// Cons: 1)

// The use of column names with space is highly discouraged.
// If you insist a column name such as Asignación_salarial
// can be replaced as `Asignación salarial`.
// I WILL NOT USE col names with space because SQL queries
// from BASH script do not accept `Asignación salarial` sintax.

// Salario = Asignación salarial
// Vacantes = Número de vacantes
// Cierre = Cierre de inscripciones
// Keywords = Palabras clave

//************************************************************

function parse_jobOffer_html($jobInfo_arrObj){
    //$arr = (array) $arrObj_elems; // old version
    //$arr = $arrObj_elems->getArrayCopy(); // getArrayCopy is not recognizing the input data type (?)
    $res = new ArrayObject(array());
    foreach($jobInfo_arrObj as $item){
        if(str_contains($item, 'Página:')){ // 0
            $res['pagina'] = trim(explode(': ', $item)[1]);
        }
        if(str_contains($item, 'Nivel:')){ // 1
            $res['nivel'] = trim(explode(': ', $item)[1]);
        }
        if(str_contains($item, 'Denominación:')){ // 2
            $res['denominacion'] = trim(explode(': ', $item)[1]);
        }
        if(str_contains($item, 'Grado:')){ // 3
            $res['grado'] = trim(explode(': ', $item)[1]);
        }
        if(str_contains($item, 'Código:')){ // 4
            $res['codigo'] = trim(explode(': ', $item)[1]);
        }
        if(str_contains($item, 'OPEC:')){ // 5
            $res['opec'] = trim(explode(': ', $item)[1]);
        }
        if(str_contains($item, 'ID único entidad:')){ // 6
            $res['entidad_codigo'] = trim(explode(': ',$item)[1]); // small int
        }
        if(str_contains($item, 'Asignación salarial:')){ // 7
            $res['salario'] = explode(': ', $item)[1];
            $res['salario'] = trim(str_replace('$', '', $res['salario']));
            $res['salario'] = substr_replace($res['salario'], ".", -3, 0);
            $res['salario'] = substr_replace($res['salario'], "'", -7, 0);
        }
        if(str_contains($item, 'Vigencia salarial:')){ // 8
            $res['vigencia_salarial'] = trim(explode(': ',$item)[1]); // year
        }
        if(str_contains($item, 'CONVOCATORIA')){ // 9
            $res['convocatoria'] = trim($item);
        }
        if(str_contains($item, 'Cierre de inscripciones')){ // 10
            $res['cierre'] = trim(explode(': ', $item)[1]); // fecha del cierre de la convocatoria
            if($res['cierre'] == 'por definir'){ $res['cierre'] = '1000-01-01'; }
            $arr_date = explode('-', $res['cierre']);
            $year = $arr_date;
            if(count($arr_date) === 1 AND strlen($year) === 4 AND strlen((int) $year) === 4){
                $res['cierre'] = $year. '-01-01';
            }
        }
        if(str_contains($item, 'Estudio:')){ // 11
            $res['estudio'] = trim(explode(': ', $item, 2)[1]);
        }
        if(str_contains($item, 'Experiencia:')){ // 12
            $res['experiencia'] = trim(explode(': ',$item)[1]); // string
            $find = array('<br>', '<p>', '</p>', '<li>', '</li>');
            $replace = '';
            $res['experiencia'] = trim(str_replace($find, $replace, $res['experiencia']));
        }
        if(str_contains($item, 'Dependencia:')){ // 16
            if (count(explode(': ', $item, 2)) > 1){
                $res['dependencia'] = trim(explode(': ', $item,2)[1]);
            }
        }
        if(str_contains($item, 'Municipio:')){ // 17
            if (count(explode(': ', $item)) > 1){
                $res['municipio'] = trim(explode(': ', $item)[1]);
                if(stripos($res['municipio'], "Bogot") !== false) {
                    $res['municipio'] = "Bogotá D.C.";
                }
            }
        }
        if(str_contains($item, 'Vacantes:')){ // 18
        //if(str_contains($item, 'Total de vacantes del Empleo:')){ // 18 // alt approach
            $aux = explode(',', $item, 2)[0];
            if (count(explode(': ', $aux)) > 1){
                $res['vacantes'] = trim(explode(': ',$aux)[1]);
            }
        }
        if(str_contains($item, 'Otros:')){ // 19
            $res['otros'] = trim(explode(': ',$item)[1]); // string
            $find = array('<br>', '<p>', '</p>', '<li>', '</li>');
            $replace = '';
            $res['otros'] = trim(str_replace($find, $replace, $res['otros']));
        }
    }
    return res;
}

function parse_jobOffer_api($jobInfo_arrObj){
    // TODO: Write down the data type of each field below.
    // Make sure both approaches have fields with the same data type.
    $res = new ArrayObject(array());
    $res['pagina'] = $jobInfo_arrObj['pagina']; // int
    $res['nivel'] = $jobInfo_arrObj['empleo']['gradoNivel']['nivelNombre']; // str
    $res['denominacion'] = $jobInfo_arrObj['empleo']['denominacion']['nombre']; // str
    $res['grado'] = $jobInfo_arrObj['empleo']['gradoDenominacion']['grado']; // int
    $res['codigo'] = $jobInfo_arrObj['empleo']['codigoEmpleo']; // str
    $res['opec'] = $jobInfo_arrObj['empleo']['id']; // int
    // $res['...'] = $jobInfo_arrObj['empleo']['convocatoria']['entidad']['id'];
    $res['entidad_codigo'] = $jobInfo_arrObj['empleo']['identificador']; // int
    $res['salario'] = $jobInfo_arrObj['empleo']['asignacionSalarial']; // str
    $res['vigencia_salarial'] = $jobInfo_arrObj['empleo']['vigenciaSalarial']; // int
    $res['convocatoria'] = $jobInfo_arrObj['empleo']['convocatoria']['nombre']; // str
    $res['cierre'] = $jobInfo_arrObj['fechaInscripcion']; // date
    // Here append to $res['estudio'] all the values of $jobInfo_arrObj['empleo']['requisitosMinimos'][i]['estudio'];
    // Update: I suspect that almost always if not always,  $jobInfo_arrObj['empleo']['requisitosMinimos'] is a single element
    // array, hence I will simply take the first element.
    // In principle, you could make the field 'estudio' an str array and then in job_offer it could be an int array.
    // Perhaps to implement in the future.
    $res['estudio'] = $jobInfo_arrObj['empleo']['requisitosMinimos'][0]['estudio']; // str
    $res['experiencia'] = $jobInfo_arrObj['empleo']['requisitosMinimos'][0]['experiencia']; // str
    // Often there're many vacantes per opec
    //$res['dependencia'] = $jobInfo_arrObj['empleo']['vacantes'][index]['dependencia']; // new
    $res['dependencia'] = $jobInfo_arrObj['empleo']['vacantes'][0]['dependencia']['nombre']; // compatible with non api approaches
    // The muncipio api field includes departamento. The municipio api field info can be a stored as int array
    // and a separate table can map that id to municipio y su departamento. But this works only
    // for the api approach.
    // $res['municipio'] = $jobInfo_arrObj['empleo']['vacantes'][index]['municipio']['nombre']; // new
    // $res['departamento'] = $jobInfo_arrObj['empleo']['vacantes'][index]['municipio']['departamento']['nombre']; // new
    // Below is the version of muncipio field compatible with the existing schema which supports non api approaches
    $res['municipio'] = '';
    $res['departamento'] = '';
    foreach($jobInfo_arrObj['empleo']['vacantes'] as $d){
      // $res['dependencia'] .= $d['dependencia']. ', '; // discarded (often is just repetition)
      $res['municipio'] .= $d['municipio']['nombre']. ', ';
      // TODO: Conciliate with non-api approaches.
      // Non-api approaches is encoding departamento_id
      // Non-api approaches lead to integrity failure
      // because it stores as many duplicates of a given
      // opec num as num of different departmentos in
      // the list of territorial vacantes. All these
      // duplicates will have the same value of municipio
      // which will be a string listing all the different
      // municipios in the list of territorial vacacantes.
      // The generation of the list of municipios is probably
      // in post_process_1() which goes before insert2db()
      // in the get_jobs.php workflow.
      // To be compatible with non-api approaches, don't
      // fetch departamento from the api response but as
      // an external parameter.
      // $res['departamento'] .= $d['municipio']['departamento']['nombre']. ', '; // str
    }
    // $res['dependencia'] = substr($res['dependencia'], 0, -2);
    $res['municipio'] = substr($res['municipio'], 0, -2);
    // $res['departamento'] = substr($res['departamento'], 0, -2);
    // The cantidad de vacantes below is the num of vacantes per location. The non api approaches and the vacantes tbl col
    // records the total num of vacantes, which aggregates on all locations. Find out where the api reports total vacantes.
    //$res['vacantes'] = $arr_job_data['empleo']['vacantes'][index]['cantidad']; // new, TODO: set int array
    $res['vacantes'] = 0;
    foreach($jobInfo_arrObj['empleo']['vacantes'] as $d){
        $res['vacantes'] += $d['cantidad'];
    }
    //$res['vacantes'] = $jobInfo_arrObj['empleo']['vacantes'][index]['disponible']; // new? TODO: Find out wich one is
    $res['otros'] = $jobInfo_arrObj['empleo']['requisitosMinimos'][0]['otros']; // str
    //$res['estadoInscripcion'] = $jobInfo_arrObj['estadoInscripcion']; // new
    //$res['InscripcionId'] = $jobInfo_arrObj['inscripcionId']; // new
    // $res['is_ascenso'] = $jobInfo_arrObj['empleo']['concursoAscenso']; // new field: bool
    // $res['gradoNivel'] = $jobInfo_arrObj['empleo']['gradoNivel']; // new field
    // QUESTION: What is the difference between ['gradoDenominacion']['grado'] and ['gradoNivel']['grado']
    // $res['entidad'] = $jobInfo_arrObj['empleo']['entidad']; // new field: bool
    // $res['is_discapacidad'] = $jobInfo_arrObj['empleo']['condicionDiscapacidad']; // new field: bool
    // $res['simo_created_at'] = $arr_job_data['empleo']['createdDate']; // new field
    // $res['descripcion'] = $jobInfo_arrObj['empleo']['descripcion']; // new field
    // $res['funciones'] = $jobInfo_arrObj['empleo']['funciones']; // new field
    // The api field alternativas is a dict array. We could use JSON type or use int array and another table to map to str
    // $res['alternativas'] = $jobInfo_arrObj['empleo']['requisitosMinimos'][0]['alternativas']; // TODO: add new col
    // with keys: 'estudio', 'experiencia' and 'otros'
    // $res['equivalencias'] = $jobInfo_arrObj['empleo']['alternativas']; // new field
    return $res;
}

/*
function get_api_data($url){ // not working
    $options = [
        'http' => [
            'header' => "Accept: application/json\r\n",
            'method' => 'GET'
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $data = json_decode($response, true);
}
*/

function get_api_data($url){
    // Example: search by departamento
    // https://simo.cnsc.gov.co/empleos/ofertaPublica/?search_departamento=1&page=0&size=10

    // Parse the URL to get the query part
    $query_string = parse_url($url, PHP_URL_QUERY);

    // Parse the query string into an array
    parse_str($query_string, $query_params);

    // Now you can access the page parameter
    $page_value = isset($query_params['page']) ? $query_params['page'] : null;

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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
    foreach($data as &$j){ // Use &, otherwise $j is a copy
        $j['pagina'] = $page_value;
    }
    return $data;
}

function job_snapshot_insert($conn, $data){
    $stmt = $conn->prepare(
        <<<EOD
        INSERT INTO job_offer_snapshot (
            nivel,
            denominacion,
            grado,
            codigo,
            opec,
            salario,
            vigencia_salarial,
            convocatoria,
            entidad_codigo,
            cierre,
            vacantes,
            estudio,
            experiencia,
            dependencia,
            municipio,
            otros
        ) VALUES (
            :nivel,
            :denominacion,
            :grado,
            :codigo,
            :opec,
            :salario,
            :vigencia_salarial,
            :convocatoria,
            :entidad_codigo,
            :cierre,
            :vacantes,
            :estudio,
            :experiencia,
            :dependencia,
            :municipio,
            :otros
        /*  No need to condition the INSERT since
            UNIQUE key takes care of it */
        ) ON DUPLICATE KEY
        UPDATE
            updated_at = NOW(),
            departamento_ids = departamento_ids | :departamento_id;
        EOD);
    if(isset($data['nivel'])){ // 1
        $stmt->bindValue(':nivel', trim($data['nivel']));
    } else {
        $stmt->bindValue(':nivel', 'NONE');
    }
    if(isset($data['denominacion'])){ // 2
        $stmt->bindValue(':denominacion', trim($data['denominacion']));
    } else {
        $stmt->bindValue(':denominacion', 'NONE');
    }
    if(isset($data['grado'])){ // 3
        $stmt->bindValue(':grado', trim($data['grado']));
    } else {
        $stmt->bindValue(':grado', -1);
    }
    if(isset($data['codigo'])){ // 4
        $stmt->bindValue(':codigo', trim($data['codigo']));
    } else {
        $stmt->bindValue(':codigo', 'NONE');
    }
    if(isset($data['opec'])){ // 5
        $stmt->bindValue(':opec', trim($data['opec']));
    } else {
        $stmt->bindValue(':opec', -1);
    }
    if(isset($data['entidad_codigo'])){ // 6
        $stmt->bindValue(':entidad_codigo', trim($data['entidad_codigo']));
    } else {
        $stmt->bindValue(':entidad_codigo', -1);
    }
    if(isset($data['salario'])){ // 7
        $stmt->bindValue(':salario', trim($data['salario']));
    } else {
        $stmt->bindValue(':salario', 'NONE');
    }
    if(isset($data['vigencia_salarial'])){ // 8
        $stmt->bindValue(':vigencia_salarial', trim($data['vigencia_salarial']));
    } else {
        $stmt->bindValue(':vigencia_salarial', 0000);
    }
    if(isset($data['convocatoria'])){ // 9
        $stmt->bindValue(':convocatoria', trim($data['convocatoria']), 2);
    } else {
        $stmt->bindValue(':convocatoria', 'NONE', 2);
    }
    if(isset($data['cierre'])){ // 10
        $stmt->bindValue(':cierre', trim($data['cierre']));
    } else {
        $stmt->bindValue(':cierre', '0000-00-00');
    }
    if(isset($data['estudio'])){ // 11
        $stmt->bindValue(':estudio', trim($data['estudio']));
    } else {
        $stmt->bindValue(':estudio', 'NONE');
    }
    if(isset($data['experiencia'])){ // 12
        $stmt->bindValue(':experiencia', trim($data['experiencia']));
    } else {
        $stmt->bindValue(':experiencia', 'NONE');
    }
    if(isset($data['dependencia'])){ // 13
        $stmt->bindValue(':dependencia', trim($data['dependencia']));
    } else {
        $stmt->bindValue(':dependencia', 'NONE');
    }
    if(isset($data['municipio'])){ // 14
        $stmt->bindValue(':municipio', trim($data['municipio']));
    } else {
        $stmt->bindValue(':municipio', 'NONE');
    }
    if(isset($data['vacantes'])){ // 15
        $stmt->bindValue(':vacantes', trim($data['vacantes']));
    } else {
        $stmt->bindValue(':vacantes', -1);
    }
    if(isset($data['otros'])){ // 16
        $stmt->bindValue(':otros', trim($data['otros']));
    } else {
        $stmt->bindValue(':otros', 'NONE');
    }
    $stmt->execute();

}

function insert2db($conn, $data){
    job_snapshot_insert($conn, $data);
}

function persist($conn, $db, $pages_batch){
    try{
        $conn = new adminPDO($db);
        foreach($pages_batch as $jobsPerPage){
            $arr = $jobsPerPage->getArrayCopy();
            if ($arr) {
            }
            foreach($jobsPerPage as $jobInfo){
                // BEGIN Test
                //$pagina = ((array) $jobInfo_arrObj)[0]; // test
                //$pagina = trim(explode(':', $pagina)[1]); // test
                // END Test

                // BEGIN non-API approaches
                //$parsed_jobInfo_arrObj = parse_jobOffer_html($jobInfo_arrObj);
                // END non-API approaches

                // BEGIN API approach
                $parsed_jobInfo = parse_jobOffer_api($jobInfo);
                // END API approach
                insert2db($conn, $parsed_jobInfo);
            }
        }
        set_cursor($conn, 'simo_website_cursor', $page);
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        $conn = null;
    }
}
?>

