<!DOCTYPE html>
<html>

<!--
Content: Display SQL Tables with pagination
Source: www.javatpoint.com/php-pagination

Browser address:
http://localhost/web-projects/scraping_SIMO/index.php

Author: 20198338 <ciudadania.ab@gmail.com>
-->

    <head>
        <title>SimoEx</title>
        <meta name="viewport" charset="utf-8" content="width=device-width, initial-scale=1">

        <!-- More: http://www.webweaver.nu/html-tips/favicon.shtml -->
        <link rel="shortcut icon" href="favicon.ico">


        <!-- Bootstrap 3 HMTL Framework (plugin) -->
        <!--
        <link rel="stylesheet"
            href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
        -->

        <!-- Bootstrap 5 HTML Framework (plugin)
             https://getbootstrap.com/docs/5.1/getting-started/introduction/
        -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
            crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
            crossorigin="anonymous"></script>

        <!-- Load search icon library
        www.w3schools.com/howto/howto_css_search_button.asp
        nothing here
        -->

        <!-- Load arrow icon script src
        www.w3schools.com/icons/tryit.asp?icon=fas_fa-angle-left&unicon=f104
        -->
        <script src='https://kit.fontawesome.com/1d6d59d2e9.js' crossorigin='anonymous'></script>

        <!-- Twitter Bootstrap: Button to match the style of the select menu with selectBoxIt

        www.c-sharpcorner.com/UploadFile/736ca4/twitter-bootstrap-3-layout-and-buttons/
        -->

        <!-- Bootstrap HTML Framework (from local file) -->
        <!-- Uncommentd in original config
        <link href="bootstrap/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
        <link href="bootstrap/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet" />
        <script src="bootstrap/bootstrap/js/bootstrap.min.js"></script>
        -->

        <!--
             To handle long text in select options
             Required links:
             gregfranko.com/jquery.selectBoxIt.js/#GettingStarted
             Theme: SelectBoxIt with Twitter Bootstrap
        -->
        <link type="text/css" rel="stylesheet" href="http://netdna.bootstrapcdn.com/twitter-bootstrap/2.2.2/css/bootstrap-combined.min.css" />
        <link type="text/css" rel="stylesheet" href="http://gregfranko.com/jquery.selectBoxIt.js/css/jquery.selectBoxIt.css" />

        <!-- My custom CSS-->
        <!-- Uncommented in original config -->
        <link rel="stylesheet" type="text/css" href="mystyle.css">
    </head>
    <body>
        <!-- <p id="demo"></p> -->
        <?php

            $items_per_page = 5;  // entries per page

            if (isset($_GET["page"])) {
                $page  = intval($_GET["page"]);
            }
            else {
                $page = 1;
            }

            if (isset($_GET["dept"])) {
                $dept_id_param = intval($_GET["dept"]);
            }
            else {
                $dept_id_param = -1;
            }

            if (isset($_GET["width"])) {
                $width = intval($_GET["width"]);
            }

            //***********************************
            // Get total pages...
            //***********************************

            // $today = date("Y-m-d", strtotime('-1 year')); // '0000-00-00';

            require_once __DIR__ . '/../vendor/autoload.php';
            use Utils\Connectivity\Database;

            $dbname = 'simo';
            try {
                $conn = Database::public($dbname);
            } catch (PDOException $e) {
                echo 'Connection failed: ' . $e->getMessage();
            }

            // ORDER BY to guarantee No_Aplica option is last.
            $query = "
                SELECT DISTINCT nombre FROM departamento
                ORDER BY CASE WHEN nombre = 'No_Aplica' THEN 1 ELSE 0 END, nombre
            ";
            $stmt = $conn->query($query);
            $dept_id_to_dept_str = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $query = "SELECT opec FROM empleo WHERE fecha_inscripcion >= date(now())";
            if($dept_id_param !== -1){
                $query .= " AND id IN (
                        SELECT empleo_id FROM empleo_vacante
                        WHERE vacante_id IN (
                            SELECT id FROM vacante
                            WHERE municipio_id IN (
                                SELECT id FROM municipio
                                WHERE departamento = :dept_str
                            )
                        )
                    )";
            }
            $query .= " ORDER BY fecha_inscripcion";
            $stmt = $conn->prepare($query);
            if($dept_id_param !== -1){
                $stmt->bindParam(':dept_str', $dept_id_to_dept_str[$dept_id_param]);
            }
            $stmt->execute();
            $all_opecs = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $total_records = count($all_opecs);
            echo "</br>";
            $total_pages = ceil($total_records / $items_per_page);

            $start_from = ($page-1) * $items_per_page;
            $page_opecs = array_slice($all_opecs, $start_from, $items_per_page);

            $dept_count = count($dept_id_to_dept_str);
            $stmt = null;
            if(count($page_opecs) > 0){
                $placeholders = implode(',', array_fill(0, count($page_opecs), '?'));
                if($dept_id_param === -1) {
                    $lugar_subquery = "(SELECT GROUP_CONCAT(DISTINCT dep.iso ORDER BY dep.iso SEPARATOR ', ')
                         FROM empleo_vacante ev
                         JOIN vacante v ON v.id = ev.vacante_id
                         JOIN municipio m ON m.id = v.municipio_id
                         JOIN departamento dep ON dep.nombre = m.departamento
                         WHERE ev.empleo_id = e.id) AS lugar";
                } else {
                    $dept_str_quoted = $conn->quote($dept_id_to_dept_str[$dept_id_param]);
                    $lugar_subquery = "(SELECT GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR ', ')
                         FROM empleo_vacante ev
                         JOIN vacante v ON v.id = ev.vacante_id
                         JOIN municipio m ON m.id = v.municipio_id
                         WHERE ev.empleo_id = e.id
                         AND m.departamento = $dept_str_quoted) AS lugar";
                }
                $query = "
                    SELECT
                        e.opec,
                        e.nivel_nombre AS nivel,
                        d.nombre AS denominacion,
                        e.asignacion_salarial AS salario,
                        e.fecha_inscripcion AS cierre,
                        '' AS estudio,
                        '' AS keywords,
                        $lugar_subquery
                    FROM empleo e
                    LEFT JOIN denominacion d ON d.id = e.denominacion_id
                    WHERE e.opec IN ($placeholders)
                    ORDER BY e.fecha_inscripcion
                ";
                $stmt = $conn->prepare($query);
                $stmt->execute($page_opecs);
            }
        ?>

        <div class="container">
            <div class="center-align">
                <h1>SIMO Express</h1>
                <p style="margin-bottom:16px;">
                    <i>Ofertas de empleo público en Colombia</i>
                </p>
                <p style="margin-bottom:32px;">
                    Visite la página oficial:<br>
                    <a href="https://simo-ppal.cnsc.gov.co/#ofertaEmpleo"><i>Sistema de apoyo para la Igualdad, el Mérito y la Oportunidad</i> (SIMO)</a>
                </p>
                <p style="margin-bottom:32px;">
                    <!-- Sobre este sitio web:<br> -->
                    <a href="about.html"><i>Sobre este sitio web</i></a> <!-- Veeduría ciudadana -->
                    <!-- </p> -->
                    &nbsp;
                    |
                    &nbsp;
                    <!-- <p style="margin-bottom:32px;"> -->
                    <!-- Análisis de los datos reportados:&nbsp; -->
                    <a href="insight.php"><i>Análisis de los datos reportados</i></a> <!-- Veeduría ciudadana -->
                </p>
            </div>

            <div class="buscador-container">

                <!--******************-->
                <!--** Search Depto **-->
                <!--******************-->

                <p>Buscar por departamento:</p>

                <!--
                onchange:
                stackoverflow.com/questions/647282/is-there-an-onselect-event-or-equivalent-for-html-select
                -->

                <select id="dept" onChange="go2Dept();">
                <?php
                    if($dept_id_param == -1) {
                        echo "<option selected value=-1> -- todos los deptos -- </option>";
                    }else{
                        echo "<option value=-1> -- todos los deptos -- </option>";
                    }
                    $i = 0;
                    for($x = 0; $x<$dept_count; $x++) {
                        if($dept_id_param == $i) {
                            echo "<option selected value=$i>". $dept_id_to_dept_str[$x]. "</option><br>";
                        }else {
                            echo "<option value=$i>". $dept_id_to_dept_str[$x]. "</option><br>";
                        }
                        $i++;
                    };
                ?>
                </select>

                <!--*****************-->
                <!--** Search Page **-->
                <!--*****************-->

                <!--***** BEGIN comment *******
                Adjust column width (not working):
                stackoverflow.com/questions/928849/setting-table-column-width
                    ***** END comment **********-->

                <br>
                <br>
                <p><span style="font-size:normal;">P&aacute;gina (m&aacute;x. <?php echo ($total_pages > 0) ? $total_pages : 1;?>):</span></p>

                <table class="table table-bordered" style="width:30%;">
                <tr>
                <td>

                <!-- ********** BEGIN comment ******
                Alternatives:
                www.w3schools.com/bootstrap/bootstrap_forms_sizing.asp
                <div class="col-xs-3">
                     ********** END comment ******** -->

                <input id="page" type="text" placeholder="<?php echo $page; ?>" required>
                </td>
                <td>
                <button class="btn" onClick="go2Page();"><i class="fa fa-search"></i></button>
                </td>
                </tr>
                </table>
            </div>

            <!--*********************-->
            <!--** Jobs Data Table **-->
            <!--*********************-->

            <div class="hscroll">
                <table class="table table-striped table-condensed table-bordered">
                    <thead>
                        <tr>
                        <th>Palabras clave</th>
                        <th class="no-break"><?php echo $dept_id_param === -1 ? 'Departamento' : 'Municipio'; ?></th>
                        <th>Salario</th>
                        <th>Cierre de inscripciones</th>
                        <th>OPEC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            while ($stmt && $row = $stmt->fetch(PDO::FETCH_BOTH)) {
                                // Display each field of the records.
                        ?>
                        <tr>
                        <td><?php
                            // TODO make func with parsing below and add unit test to it
                            $estudio = '';
                            $nivel = strtolower($row["nivel"]);
                            $denom = strtolower($row["denominacion"]);
                            if($denom === 'profesional universitario'){
                                $estudio = 'Profesional. ';
                                $denom = '';
                            }
                            if($denom === 'profesional especializado'){
                                $estudio = 'Profesional especializado. ';
                                $denom = '';
                            }
                            if(!$estudio and str_contains($row["estudio"], 'PROFESIONAL') and !str_contains($nivel, 'profesional')){
                                $estudio = 'Profesional. ';
                            }
                            if(str_contains($estudio, 'Profesional') and $nivel === 'profesional'){
                                $nivel = '';
                            }
                            $nivel = str_contains($denom, $nivel)? '' : $row["nivel"]. '. ';
                            $denom = str_replace('tecnico','técnico', $denom);
                            $denom = $denom? ucfirst($denom). '. ' : '';
                            if(!$estudio and $row["keywords"] === 'Bachiller'){
                                $estudio = 'Bachiller';
                                $keywords = '';
                            } else {
                                $keywords = $row["keywords"]? $row["keywords"]. '.' : '';
                            }
                            $text = $estudio. $nivel. $denom. $keywords;

                            if(isset($_GET["width"])){
                                if($_GET["width"] < 992){
                                    $text = wordwrap($text, 50, "<br>", false);
                                }
                            }
                            echo "$text";
                            ?></td>
                        <td class="no-break"><?php
                            $lugar = $row["lugar"] ?? '';
                            echo wordwrap($lugar, 30, "<br>", false);
                        ?></td>
                        <td><?php echo $row["salario"]; ?></td>
                        <td><?php echo $row["cierre"] === null ? 'sin definir' : $row["cierre"]; ?></td>
                        <td><?php echo $row["opec"]; ?></td>
                        </tr>
                        <?php
                            };
                            // Close the connection
                            $conn = null;
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php
                    $pagLink = "";
                    $here = basename(__FILE__); // name of current file

                    if ($page > 1) {
                        echo "<a class='arrows' href='". $here. "?width=".$width . "&page=".($page-1). "&dept=". $dept_id_param. "' style='margin-right: 10px;'>
                                <i class='fas fa-angle-left' style='font-size:24px'></i>
                              </a>";
                    }

                    $pagLink .= "<span class='active' href='' style='margin: 0 10px;'>".$page."</span>";
                    echo $pagLink;

                    if ($page < $total_pages) {
                        echo "<a class='arrows' href='". $here. "?width=".$width. "&page=".($page+1). "&dept=". $dept_id_param. "' style='margin-left: 10px;'>
                                <i class='fas fa-angle-right' style='font-size:24px'></i>
                              </a>";
                    }
                ?>
            </div>
        </div>

        <script>
            window.onload = function ()
            {
                //let width = screen.width; // physical screen width
                var width = window.innerWidth; // omits browser's frame
                var bool = "<?php echo isset($_GET['width']);?>";
                var here = "<?php echo $here;?>";
                if(!bool){
                    window.location.href = here+'?width='+width;
                }
            }

            function go2Dept()
            {
                var width = "<?php echo $_GET['width'];?>";
                var bool = "<?php echo isset($_GET['width']);?>";
                var dept = document.getElementById("dept").value;
                var here = "<?php echo $here;?>";
                window.location.href = here+'?width='+width+'&page='+1+'&dept='+dept;
            }

            function go2Page()
            {
                var width = "<?php echo $_GET['width'];?>";
                var bool = "<?php echo isset($_GET['width']);?>";
                var page = document.getElementById("page").value;
                var dept = "<?php echo $dept_id_param;?>";
                var here = "<?php echo $here;?>";
                var totalPages = "<?php echo $total_pages; ?>";
                page = ((page > totalPages) ? totalPages: ((page < 1) ? 1 : page));
                window.location.href = here+'?width='+width+'&page='+page+'&dept='+dept;
            }

            // Not possible while using Bootstrap:
            // set input field size to placeholder length:
            //input.setAttribute('size',30px);
            //input.getAttribute('placeholder').length

            // Constraint Validation:
            // developer.mozilla.org/en-US/docs/Web/Guide/HTML/Constraint_validation

            //function checkInput() {
            //var page = document.getElementById("page");
            //var tot = "<?=$total_pages?>";
            //console.log(tot);
            //if (typeof page !== "undefined") {
            //if (page < 1 || page > tot) {
            //page.setCustomValidity("Page number out of range");
            //return;
            //}
            //}
            // No custom constraint violation
            //page.setCustomValidity("");
            //}

            //window.onload = function () {
            //document.getElementById("page").onchange = checkInput;
            //}
        </script>

        <!-- To handle long text in select options
             gregfranko.com/jquery.selectBoxIt.js/#GettingStarted
             Example: //jsfiddle.net/ZTs42/2/
        -->

        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
        <script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js"></script>
        <script src="http://gregfranko.com/jquery.selectBoxIt.js/js/jquery.selectBoxIt.min.js"></script>

        <script>
            $(function(){
            // "select" or specific target "#in_this_id_apply_selectBoxIt"
              $("select").selectBoxIt({
                                      theme: "default",
                                      autoWidth: false
                                      });
              });
        </script>
    </body>
</html>

