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
        <title>SimoEx:Insight</title>
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
        <?php
            // Import file where we define connection to Database
            require_once "/var/www/html/simo-express/connectivity.php";
            try {
                // $today = date("Y-m-d", strtotime('-1 year')); // '0000-00-00';
                $dbname = 'simo';
                $conn = new publicPDO($dbname);
                $query = "SELECT count(*) FROM vw_job_offer WHERE cierre >= date(now()) OR cierre = '1000-01-01'";
                $stmt = $conn->query($query);
                $total = $stmt->fetchColumn();
                $query = "SELECT count(*) FROM vw_job_offer WHERE cierre >= date(now())";
                $stmt = $conn->query($query);
                $vigentes = $stmt->fetchColumn();
                # 'por definir' is encoded as '1000-01-01' and NULL as '0000-00-00'
                $query = "SELECT count(*) FROM vw_job_offer WHERE cierre = '1000-01-01'";
                $stmt = $conn->query($query);
                $por_definir = $stmt->fetchColumn();
                // Using job_offer instead of vw_job_offer because the latter doesn't have created_at column
                // Adding filter vacantes > 0 so that it is faithful to vw_job_offer data.
                $query = "SELECT count(*)
                          FROM job_offer
                          WHERE created_at < cierre AND vacantes > 0";
                $stmt = $conn->query($query);
                $validas = $stmt->fetchColumn();
            } catch (PDOException $e) {
                echo "Error: ". $e->getMessage(). PHP_EOL;
            } finally {
                $conn = null;
            }
        ?>
        <div class="container">
            <center>
            <h2>Análisis de datos reportados</h2>
            <p><!-- <b>Datos:</b> -->Ofertas de trabajo publicadas en la sección <a href="https://simo-ppal.cnsc.gov.co/#ofertaEmpleo">#ofertaEmpleo</a> de la plataforma <a href="https://simo-ppal.cnsc.gov.co">SIMO</a>.</p>
            <p>Total de ofertas<sup><a href="#fn1" id="ref1">1</a></sup>: <?php echo $total;?><br>
            Número de ofertas vigentes: <?php echo $vigentes;?><br>
            Número de ofertas con fecha de cierre "por definir": <?php echo $por_definir;?><br>
            Número de ofertas publicadas al menos un día antes de su fecha de cierre (aprox.): <?php echo $validas;?>
<hr></hr>
        <sup id="fn1">1. Cada oferta se identifica por su código <a href="https://simo.cnsc.gov.co/cnscwiki/doku.php?id=simo:documentos:manual_ciudadano#mis_empleos">OPEC</a> y puede tener más de una vacante.  Las ofertas con fechas de inscripción vencidas o con cero número de vacantes no son incluidas en el análisis.<a href="#ref1" title="Jump back to footnote 1 in the text.">↩</a></sup>
<!-- Las ofertas sin número de vacantes reportado no son incluidas en el análisis. -->
        </p>
        </div>
    </body>
</html>
