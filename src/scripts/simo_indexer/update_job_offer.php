<?php
// Keyword Generator:
// Extrae palabras clave (carreras, estudios, oficios, etc.) de la lista 'Estudios' de la tabla 'job_offer_snapshot'.

require 'src/utils/connectivity.php';
require 'src/utils/string_ops.php';


function find_departamento($k, $job_offer_obj, $dpto_colombia_obj){
        $municipio = $job_offer_obj[$k]['municipio'];
        $convocatoria = $job_offer_obj[$k]['convocatoria'];
        // job_offer.municipio can have more than one municipio, e.g., `Roldanillo, Cartago, Bugalagrande`.
        // Assuming that `Bogota DC` is unique per OPEC.
        if(contains($municipio, 'Bogotá D.C.')){
            return 'Bogotá D.C.';
        }else{
            foreach($dpto_colombia_obj as $i=>$j) {
                if(contains($convocatoria,$j)) {
                    return $j;
                }
            }
        }
}

function find_keywords($k, $job_offer_obj, $estudio_basico_var_obj, $estudio_especializado_var_obj, $otras_habilidades_var_obj) {
    // Remove 'EDUCACIÓN' here, otherwise wrongly taken as career 'Educación'
    $v = str_replace('EDUCACIÓN','',$job_offer_obj[$k]['estudio']);
    // Remove space between words to bypass
    // the bug of unexpected missing spaces
    // in the scraping of Estudios info.
    $v = str_replace(' ','',$v);

    $keywords = "";
    $foo = False;
    foreach($estudio_basico_var_obj as $i=>$j) {
        if(contains($v,str_replace(' ','',$j))) {
            if($foo){
                $keywords .= ", ";
            }
            $keywords .= $j;
            $foo = True;
        }
    }

    foreach($estudio_especializado_var_obj as $i=>$j) {
        if(contains($v,str_replace(' ','',$j))) {
            if($foo){
                $keywords .= ", ";
            }
            $keywords .= $j;
            $foo = True;
        }
    }

    foreach($otras_habilidades_var_obj as $i=>$j) {
        if(contains($v,str_replace(' ','',$j))) {
            if($foo){
                $keywords .= ", ";
            }
            $keywords .= $j;
            $foo = True;
        }
    }

    //********************************
    // Remove redunancies from findings e.g.
    // (Admin, Admin de Empresas,...)
    // becomes (Admin de Empresas,...)
    //********************************

    if($foo){
        $explode_keywords = explode(", ", $keywords);
        $filter_explode_keywords = $explode_keywords;

        $first_complement = array(); // Detect new careers (optional)
        $r = 0; // Detect new careers (optional)
        foreach(range(0,count($explode_keywords)-1) as $j){
            if(substr_count($keywords,$explode_keywords[$j]) > 1){
                // remove item at index $j
                unset($filter_explode_keywords[$j]);

                // first_complement: Detect new careers (optional)
                $first_complement[$r] = $explode_keywords[$j];
                $r = $r+1; // Detect new careers (optional)
            }
        }
        // filter_explode_keywords now has 'Ing. Admin. y Finanzas'
        // but not 'Ing. Admin.', 'Ing.', 'Admin.', 'Finanzas'
        // first_complement has 'Ing. Admin.', 'Ing.', 'Admin.', 'Finanzas'
        // but not 'Ing. Admin. y Finanzas'.

        // Re-index array
        $filter_explode_keywords = array_values($filter_explode_keywords);
        $reduce_keywords = implode(", ",$filter_explode_keywords);
    }

    // The following module named 'Detect new careers'
    // is OPTIONAL.  It can be commented out if you want.
    //**************************
    // BEGIN: Detect new careers
    //**************************

    //************************************
    // Example:
    // First, remove: 'Ing. Admin. y Finanzas'
    // Second, remove: 'Ing. Admin.'
    // Finally, remove: 'Ing.', 'Admin.'
    //************************************

    // Second round to remove redundancies
    if($foo){
        if(count($first_complement) > 0){
            $implode_first_complement = implode($first_complement);
            $filter_explode_reduce_keywords = $first_complement;
            $second_complement = array();
            $r = 0;
            foreach(range(0,count($first_complement)-1) as $j){
                if(substr_count($implode_first_complement,$first_complement[$j]) > 1){
                    // remove item at index $j
                    unset($filter_explode_reduce_keywords[$j]);
                    $second_complement[$r] = $first_complement[$j];
                    $r = $r+1;
                }
            }
            // filter_explode_reduce_keywords now has 'Ing. Admin.'
            // but not 'Ing.', 'Admin.', 'Finanzas'
            // second_complement has 'Ing.', 'Admin.', 'Finanzas'
            // but not 'Ing. Admin.'

            // Second round to Re-index array
            $filter_explode_reduce_keywords = array_values($filter_explode_reduce_keywords);
            $reduce_reduce_keywords = implode(", ",$filter_explode_reduce_keywords);
        } //if

        // BEGIN: first round str_replace()
        // replace 'Ing. Admin. y Finanzas'
        $reduce_v = str_replace($filter_explode_keywords, '_',$v);
        // replace 'Ing. Admin.'
        if(count($first_complement) > 0){
            $reduce_v = str_replace($filter_explode_reduce_keywords, '_',$reduce_v);
        }
        // Later (2nd round) we replace single words
        // END: first round str_replace()
    } //if

    if(!$foo){$reduce_v = $v;}

    // Remove capitalized words that are not a career.
    // WARNING: In array below, do not include letters
    // that could be part of new career name
    $not_a_career = array('Afines','afin','aprobar','Área','Áreas','Artículo','artículo','autoridad','Básico','Basico','Básicos','Basicos','cátedra','C-','capacitación', 'Conocimiento','conocimiento','competente','Curso','Cursar','Del','determinados','Decreto','demás','Disciplina','EDUCACIÓN','EDUCACION','Especialización','establecida','Formación','formación','intensidad','Ley','Licencia','Matrícula', 'Matricula','mínima','Modalidad','Nucleo','Núcleo', 'Núcleos','Nucleos','NBC','Otros','Postgrado','profesional','Profesional','programa','Relacionada','requisitos','SENA','SG','SST','Servicios','Tarjeta','Título','Titulo');
    $reduce_v = str_replace($not_a_career,'_',$reduce_v);
    $to_print_reduce_v = $reduce_v;

    // BEGIN: Remove 1st word and 1st word after dot
    // (because they are capitalized but are not a career)
    $explode_reduce_v = explode('.', $reduce_v);
    $post_explode_reduce_v = array();
    foreach(range(0,count($explode_reduce_v)-1) as $h){
        // Remove 1st word after dot
        $post_explode_reduce_v[$h]  = trim(strstr(trim($explode_reduce_v[$h]), ' '));
    }
    $reduce_v = implode('_',$post_explode_reduce_v);
    // END: Remove 1st word and 1st word after dot

    // BEGIN: 2nd round of str_replace()
    // (this time without space between words)
    if($foo){
        // replace 'Ing.Admin.yFinanzas'
        $reduce_v = str_replace(explode(',',str_replace(' ','',$reduce_keywords)), '_',str_replace(' ','',$reduce_v));
        if(count($first_complement) > 0){
            // replace 'Ing.Admin.'
            $reduce_v = str_replace(explode(',',str_replace(' ','',$reduce_reduce_keywords)), '_',$reduce_v);
            // replace 'Ing.', 'Admin.', 'Finanzas'
            $reduce_v = str_replace($second_complement, '_',$reduce_v);
        }
    }
    // END: 2nd round of str_replace()

    // Report only if captial letters remain...
    if(preg_match('/[A-Z]/', $reduce_v) && ($counter < $upper_bound)){
        $counter = $counter + 1;
        if($flag){echo "Detected possible new careers:". PHP_EOL. PHP_EOL; }
        $flag = False;

        //****** BEGIN: only for testing ******
        //echo "k = ". $k. PHP_EOL; // 4testing
        //echo "keywords: ". $keywords. PHP_EOL;
        //echo "implode(filter_explode_keywords): ". implode(',', $filter_explode_keywords). PHP_EOL;
        //if(count($filter_explode_reduce_keywords) > 0){echo "implode(filter_explode_reduce_keywords): ". implode(',',$filter_explode_reduce_keywords). PHP_EOL;}
        //if(count($second_complement) > 0){echo "implode(second_complement): ". implode(',',$second_complement). PHP_EOL;}
        //echo PHP_EOL;
        //if($k == 66){echo $v. PHP_EOL.PHP_EOL.PHP_EOL;}
        //****** END: only for testing *********

        echo "OPEC: ". $job_offer_obj[$k]["opec"]. PHP_EOL. $reduce_v. '"'. PHP_EOL. PHP_EOL;
    } // if
    //***************************
    // END: Detect new careers
    //***************************
    return $reduce_keywords;
}

try {
    $dbname = 'simo';
    $conn = new adminPDO($dbname);

    // Use $conn->exec() if no results are returned
    // Use $conn->prepare() if using bindValue()

    // Query "SELECT Estudio FROM job_offer LIMIT n OFFSET m"
    // returns only n records, starting from record m (1st item m = 0)
    $stmt = $conn->prepare("SELECT id, max_snap_id, municipio, convocatoria, estudio, opec FROM job_offer WHERE id > (SELECT value FROM cursorseq WHERE `key` = 'update_job_offer_id_seq')");
    $stmt->execute();
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $job_offer_obj = $stmt->fetchAll();

    // To update job_offer.departamento

    $stmt = $conn->prepare("SELECT nombre FROM dpto_colombia");
    $stmt->execute();
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $res = new RecursiveArrayIterator($stmt->fetchAll());
    $dpto_colombia_obj = new RecursiveIteratorIterator($res);

    // To update job_offer.keywords

    $stmt = $conn->prepare("SELECT nombre FROM estudio_basico_variaciones");
    $stmt->execute();
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $res = new RecursiveArrayIterator($stmt->fetchAll());
    $estudio_basico_var_obj = new RecursiveIteratorIterator($res);

    $stmt = $conn->prepare("SELECT nombre FROM estudio_especializado_variaciones");
    $stmt->execute();
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $res = new RecursiveArrayIterator($stmt->fetchAll());
    $estudio_especializado_var_obj = new RecursiveIteratorIterator($res);

    $stmt = $conn->prepare("SELECT nombre FROM otras_habilidades_variaciones");
    $stmt->execute();
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $res = new RecursiveArrayIterator($stmt->fetchAll());
    $otras_habilidades_var_obj = new RecursiveIteratorIterator($res);

    // limit # of new careers reports to $upper_bound
    $counter = 0; // Detect new careers
    $upper_bound = 5; // Detect new careers
    $flag = True; // Detect new careers

    $kmax = count($job_offer_obj)-1;
    if(empty($job_offer_obj)){
        $message = "WARNING: Nothing to update.";
        #error_log($message, E_WARNING);
        echo $message. PHP_EOL;
    } else {
        foreach(range(0, $kmax) as $k) {
            $sql = <<<SQL
            START TRANSACTION;

            UPDATE job_offer
            SET
                departamento_id = (SELECT id FROM dpto_colombia WHERE nombre = :dpto_nombre),
                keywords = :keywords,
                nivel_id = (SELECT id FROM nivel WHERE nombre = (SELECT nivel FROM job_offer_snapshot WHERE id = :max_snap_id))
            WHERE id = :id;

            /* UPDATE cursorseq SET value = :id WHERE `key` = 'update_job_offer_id_seq'; */

            COMMIT;

            ROLLBACK;
            SQL;
            $stmt = $conn->prepare($sql);
            // Use bindValue() with prepare() instead of exec().
            $stmt->bindValue(':id', $job_offer_obj[$k]["id"]);
            $stmt->bindValue(':max_snap_id', $job_offer_obj[$k]["max_snap_id"]);
            #$stmt->bindValue(':dpto_nombre', $dpto_colombia_nombre);
            $stmt->bindValue(':dpto_nombre', find_departamento($k, $job_offer_obj, $dpto_colombia_obj));
            #$stmt->bindValue(':keywords', $keywords);
            $stmt->bindValue(':keywords', find_keywords($k, $job_offer_obj, $estudio_basico_var_obj, $estudio_especializado_var_obj, $otras_habilidades_var_obj));
            $stmt->execute();
        } // foreach
        $stmt = $conn->prepare("UPDATE cursorseq SET value = :id WHERE `key` = 'update_job_offer_id_seq'");
        $stmt->bindValue(':id', $job_offer_obj[$k]["id"]);
        $stmt->execute();
    } // if
} catch(PDOException $e) {
    echo "Error: " . "<br>" . $e->getMessage();
} finally {
    $conn = null;
}
?>
