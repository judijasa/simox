<!DOCTYPE html>
<html>

<!--
Content: Display SQL Tables with pagination
Source: www.javatpoint.com/php-pagination

Browser address:
http://localhost/web-projects/scraping_SIMO/index.php

Author: judijasa <ciudadania.ab@gmail.com>
-->

    <head>
        <title>SimoEx</title>
        <meta name="viewport" charset="utf-8" content="width=device-width, initial-scale=1">

        <!-- More: http://www.webweaver.nu/html-tips/favicon.shtml -->
        <link rel="shortcut icon" href="favicon.ico">

        <!-- My custom CSS-->
        <!-- Uncommented in original config
        <link rel="stylesheet" type="text/css" href="mystyle.css">
        -->

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
                $dept  = intval($_GET["dept"]);
            }
            else {
                $dept = -1;
            }

            if (isset($_GET["width"])) {
                $width = intval($_GET["width"]);
            }

            //***********************************
            // Get total pages...
            //***********************************

            $today = date("Y-m-d"); // '0000-00-00';

            // Import file where we define connection to Database
            require_once "/srv/git/SIMOExpress/src/utils/connectivity.php";

            $conn = publicMySQLi('simo');
            $query = "SELECT COUNT(*) FROM vw_job_offer WHERE cierre >= '$today' OR cierre = '1000-01-01'";
            $result = mysqli_query($conn, $query);
            $row = mysqli_fetch_row($result);
            $total_records = $row[0];

            echo "</br>";
            // Number of pages required.
            $total_pages = ceil($total_records / $items_per_page);

            //*******************************
            // Get current page records...
            //*******************************

            $start_from = ($page-1) * $items_per_page;

            $query = "SELECT nombre FROM dpto_colombia";
            $result_depts = mysqli_query($conn, $query);
            $row = mysqli_fetch_all($result_depts);
            mysqli_free_result($result_depts);
            //print_r($row);
            $arr_length = count($row);
            if($dept !== -1){
                $str_dept = $row[$dept][0];
                $query = "SELECT * FROM vw_job_offer WHERE (cierre > '$today' OR cierre = '1000-01-01') AND departamento = '$str_dept' LIMIT $start_from, $items_per_page";
            } else {
                $query = "SELECT * FROM vw_job_offer WHERE cierre > '$today' OR cierre = '1000-01-01' LIMIT $start_from, $items_per_page";
            }
            $result_jobs = mysqli_query($conn, $query);
        ?>

        <div class="container">
            <center>
            <h1>SIMO Express</h1>
            <p style="margin-bottom:16px;">
            <i>Ofertas de empleo público en Colombia</i>
            </p>
            <p style="margin-bottom:32px;">
            Visite la página oficial:<br>
            <a href="https://simo-ppal.cnsc.gov.co/#ofertaEmpleo"><i>Sistema de apoyo para la Igualdad, el Mérito y la Oportunidad</i> (SIMO)</a>
            </p>
            <div align="left">

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
                    if($dept == -1) {
                        echo "<option selected value=-1> -- todos los deptos -- </option>";
                    }else{
                        echo "<option value=-1> -- todos los deptos -- </option>";
                    }


                    /*
                    The commented lines below also work,
                    but since we already fetched all data from $result_depts
                    we better not fetch again.

                    $i = 0; // initial val in option
                    while($row = mysqli_fetch_row($result_depts)) {
                        if(!$row[0]){
                            break;
                        }
                        if($dept == $i) {
                            echo "<option selected value=$i>". $row[0]. "</option><br>";
                        }else {
                            echo "<option value=$i>". $row[0]. "</option><br>";
                        }
                        $i++;
                    };
                    */

                    $i = 0;
                    for($x = 0; $x<$arr_length; $x++) {
                        if($dept == $i) {
                            echo "<option selected value=$i>". $row[$x][0]. "</option><br>";
                        }else {
                            echo "<option value=$i>". $row[$x][0]. "</option><br>";
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
                <p><span style="font-size:normal;">P&aacute;gina (m&aacute;x. <?php echo $total_pages;?>):</span></p>

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
            <th>Municipio</th>
            <th>Salario</th>
            <th>Cierre de inscripciones</th>
            <th>OPEC</th>
            </tr>
            </thead>
            <tbody>
            <?php

                while ($row = mysqli_fetch_array($result_jobs)) {
                    // Display each field of the records.
            ?>
            <tr>
            <!-- <td><?php echo $row["nivel"]. ". ". $row["keywords"]; ?></td> -->
            <td><?php
                $denom = strtolower($row["denominacion"]);
                $denom = (strtolower($row["nivel"]) === $denom)? '' : ucfirst($denom). '. ';
                $estudio = (str_contains($row["estudio"], "PROFESIONAL"))? 'Profesional. ' : '';
                $keywords = $row["keywords"]? $row["keywords"]. "." : "";
                $text = $row["nivel"]. ". ". $denom. $estudio. $keywords;
                if(isset($_GET["width"])){
                    if($_GET["width"] < 992){
                        $text = wordwrap($text, 50, "<br>", false);
                    }
                }
                echo "$text";
                ?></td>
            <td><?php
                if(stripos($row["municipio"], "Bogot") !== false){
                    echo "Bogotá D.C.";
                }else{
                    $text = $row["municipio"];
                    $newtext = wordwrap($text, 30, "<br>", false);
                    echo "$newtext";
                    //echo $row["Municipio"];
                    if(isset($row["departamento"])){
                        echo ", ". $row["departamento"];
                    }
                    //echo $row["Municipio"]. ", ". $row["Departamento"];
                }?></td>
            <td><?php echo $row["salario"]; ?></td>
            <td><?php echo $row["cierre"] === '1000-01-01'? 'sin definir' : $row["cierre"]; ?></td>
            <td><?php echo $row["opec"]; ?></td>
            </tr>
            <?php
                };
                mysqli_free_result($result_jobs);
                mysqli_close($conn);
            ?>
            </tbody>
            </table>
            </div>

            <div class="pagination">
                <?php

                    $pagLink = "";
                    $here = basename(__FILE__); // name of current file

                    if($page>1){
                        echo "<a href='". $here. "?width=".$width . "&page=".($page-1). "&dept=". $dept. "'><i class='fas fa-angle-left' style='font-size:24px'></i></a>";
                    }

                    $pagLink .= "<a class = 'active' href=''>".$page."</a>";

                    echo $pagLink;

                    if($page<$total_pages){
                        echo "<a href='". $here. "?width=".$width. "&page=".($page+1). "&dept=". $dept. "'><i class='fas fa-angle-right' style='font-size:24px'></i></i></a>";
                    }
                ?>
            </div>
            </center>
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
                var page = "<?php echo $page;?>";
                var dept = document.getElementById("dept").value;
                var here = "<?php echo $here;?>";
                window.location.href = here+'?width='+width+'&page='+page+'&dept='+dept;
            }

            function go2Page()
            {
                var width = "<?php echo $_GET['width'];?>";
                var bool = "<?php echo isset($_GET['width']);?>";
                var page = document.getElementById("page").value;
                var dept = "<?php echo $dept;?>";
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
