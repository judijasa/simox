<?php
require 'vendor/autoload.php'; // in case pdo class in vendor
function getTotalPages($tot_res_var){
    // They use 10 job items per page.  Extract the total
    // Nr of pages from the total # of results:
    $tot_pgs = 1;  // default
    if (strlen($tot_res_var) > 1) {
        if (substr($tot_res_var,-1) > 0) {
            $tot_pgs = ((int) substr($tot_res_var,0,-1)) + 1;
        } else {
            $tot_pgs = (int) substr($tot_res_var,0,-1);
        }
    }
    return $tot_pgs;
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
    $debris = array_slice($debris,0,10);
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
        $find = array("<ul class=\"sinVignetas\">","</ul>","<span class=\"requLabel\">","</span>");
        $replace = array("","","","","","");
        $e = trim(str_replace($find,$replace,$e));
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
                    $aux2 = trim(str_replace(",","",$aux));
                    $arrObj_dependencia->append($aux2);
                } elseif($a == $c) {
                    //echo $current_item. PHP_EOL;
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
        $aux = array_unique((array) $arrObj_dependencia);
        $dep = (object) ("Dependencia: ". implode(", ",$aux));
        $aux = array_unique((array) $arrObj_municipio);
        $mun = (object) ("Municipio: ". implode(", ",$aux));
        $aux = array_unique((array) $arrObj_vacantes);
        $vac = (object) ("Vacantes: ". implode(", ",$aux));
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

function last_pg_loaded(){
    try {
        $dbname="simo";
        $conn = new clientPDO($dbname);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        #$stmt = $conn->query("SELECT pagina FROM job_offer_snapshot ORDER BY id DESC LIMIT 1");
        $stmt = $conn->query("SELECT value FROM cursorseq WHERE key='simo_website_page'");
        $result = $stmt->fetch();
        if($result['Página'] == '') {
            return 0;
        }
        return $result['Página'];
    } catch(PDOException $e) {
        echo "Error: " . "<br>" . $e->getMessage();
    } finally {
        $conn = null;
    }
}

function insert2db($conn, $arrObj_job_data){
    $stmt = $conn->prepare(
        <<<EOD
        INSERT INTO job_offer_snapshot (
            pagina,
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
            :pagina,
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
        ) ON DUPLICATE KEY UPDATE id = id; /* i.e. do nothing */
        EOD);
    // Default biding
    $stmt->bindValue(':pagina', NULL);
    $stmt->bindValue(':denominacion', NULL);
    $stmt->bindValue(':grado', NULL);
    $stmt->bindValue(':codigo', NULL);
    $stmt->bindValue(':opec', NULL);
    $stmt->bindValue(':entidad_codigo', NULL);
    $stmt->bindValue(':salario', NULL);
    $stmt->bindValue(':vigencia_salarial', '1000');
    $stmt->bindValue(':convocatoria', NULL);
    $stmt->bindValue(':cierre', '1000-01-01');
    $stmt->bindValue(':vacantes', NULL);
    $stmt->bindValue(':estudio', NULL);
    $stmt->bindValue(':experiencia', NULL);
    $stmt->bindValue(':dependencia', NULL);
    $stmt->bindValue(':municipio', NULL);
    $stmt->bindValue(':otros', NULL);
    $arr = (array) $arrObj_job_data; // old version
    //$arr = $arrObj_job_data->getArrayCopy(); // getArrayCopy is not recognizing the input data type (?)
    foreach($arr as $item){
        if(str_contains($item, 'Página:')){ // 0
            $pagina = trim(explode(': ',$item)[1]);
            $stmt->bindValue(':pagina', $pagina);
        }
        if(str_contains($item, 'Nivel:')){ // 1
            $nivel = trim(explode(': ',$item)[1]);
            $stmt->bindValue(':nivel', $nivel);
        }
        if(str_contains($item, 'Denominación:')){ // 2
            $denominacion = trim(explode(': ',$item)[1]);
            $stmt->bindValue(':denominacion', $denominacion);
        }
        if(str_contains($item, 'Grado:')){ // 3
            $grado = trim(explode(': ',$item)[1]);
            $stmt->bindValue(':grado', $grado);
        }
        if(str_contains($item, 'Código:')){ // 4
            $codigo = trim(explode(': ',$item)[1]);
            $stmt->bindValue(':codigo', $codigo);
        }
        if(str_contains($item, 'OPEC:')){ // 5
            $opec = trim(explode(': ',$item)[1]);
            $stmt->bindValue(':opec', $opec);
        }
        if(str_contains($item, 'ID único entidad:')){ // 6
            $entidad_codigo = trim(explode(': ',$item)[1]); // small int
            $stmt->bindValue(':entidad_codigo', $entidad_codigo);
        }
        if(str_contains($item, 'Asignación salarial:')){ // 7
            $salario = explode(': ',$item)[1];
            $salario = trim(str_replace('$', '', $salario));
            $salario = substr_replace($salario, ".", -3, 0);
            $salario = substr_replace($salario, "'", -7, 0);
            $stmt->bindValue(':salario', $salario);
        }
        if(str_contains($item, 'Vigencia salarial:')){ // 8
            $vigencia_salarial = trim(explode(': ',$item)[1]); // year
            $stmt->bindValue(':vigencia_salarial', $vigencia_salarial);
        }
        if(str_contains($item, 'CONVOCATORIA')){ // 9
            $convocatoria = trim($item);
            $stmt->bindValue(':convocatoria', $convocatoria, 2);
        }
        if(str_contains($item, 'Cierre de inscripciones')){ // 10
            $cierre = trim(explode(': ',$item)[1]); // fecha del cierre de la convocatoria
            if($cierre == 'por definir'){ $cierre = '1000-01-01';}
            if(count(explode('-',$cierre)) == 1){$cierre = $cierre. '-01-01';}
            $stmt->bindValue(':cierre', $cierre);
        }
        if(str_contains($item, 'Estudio:')){ // 11
            $estudio = trim(explode(': ', $item,2)[1]);
            $stmt->bindValue(':estudio', $estudio);
        }
        if(str_contains($item, 'Experiencia:')){ // 12
            #$experiencia = trim(explode(': ',$item)[1]); // string
            #$stmt->bindValue(':experiencia', $experiencia);
        }
        if(str_contains($item, 'Dependencia:')){ // 16
            if (count(explode(': ',$item, 2)) > 1){
                $dependencia = trim(explode(': ',$item,2)[1]);
                $stmt->bindValue(':dependencia', $dependencia);
            } else {$dependencia = NULL;}
        }
        if(str_contains($item, 'Municipio:')){ // 17
            if (count(explode(': ', $item)) > 1){
                $municipio = trim(explode(': ', $item)[1]);
                if(stripos($municipio, "Bogot") !== false) {
                    $municipio = "Bogotá D.C.";
                }
                $stmt->bindValue(':municipio', $municipio);
            }
        }
        if(str_contains($item, 'Vacantes:')){ // 18
            $vacantes = explode(',',$item,2)[0];
            if (count(explode(': ',$vacantes)) > 1){
                $vacantes = trim(explode(': ',$vacantes)[1]);
                $stmt->bindValue(':vacantes', $vacantes);
            }
        }
        if(str_contains($item, 'Otros:')){ // 17
            $otros = trim(explode(': ',$item)[1]); // string
            //$stmt->bindValue(':otros', $otros);
        } #else {
            #$errorMessage = "Substring 'Otros:' not found.";
            #trigger_error($errorMessage, E_USER_WARNING);
        #}
    }
    $stmt->execute();
}
?>
