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
        <title>SimoEx — análisis</title>
        <meta name="viewport" charset="utf-8" content="width=device-width, initial-scale=1">

        <!-- More: http://www.webweaver.nu/html-tips/favicon.shtml -->
        <link rel="shortcut icon" href="favicon.ico">

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

        <!-- Load arrow icon script src
        www.w3schools.com/icons/tryit.asp?icon=fas_fa-angle-left&unicon=f104
        -->
        <script src='https://kit.fontawesome.com/1d6d59d2e9.js' crossorigin='anonymous'></script>

        <!-- My custom CSS -->
        <link rel="stylesheet" type="text/css" href="mystyle.css">
    </head>
    <body>
        <?php
            require_once __DIR__ . '/../vendor/autoload.php';
            use Utils\Connectivity\Database;

            $year_ago = date('Y-m-d', strtotime('-1 year'));
            try {
                // $today = date("Y-m-d", strtotime('-1 year')); // '0000-00-00';
                $dbname = 'simo1';
                $conn = Database::connectTo($dbname, 'simox');
                $query = "SELECT count(*) FROM empleo WHERE fecha_inscripcion >= date(now()) OR fecha_inscripcion IS NULL";
                $stmt = $conn->query($query);
                $total = $stmt->fetchColumn();
                $query = "SELECT count(*) FROM empleo WHERE fecha_inscripcion >= date(now())";
                $stmt = $conn->query($query);
                $vigentes = $stmt->fetchColumn();
                $query = "
                    SELECT count(*) FROM empleo
                    WHERE fecha_inscripcion IS NULL AND created_date >= NOW() - INTERVAL 1 YEAR";
                $stmt = $conn->query($query);
                $por_definir = $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log('simox: database connection failed: ' . $e->getMessage());
                echo 'Error de conexión con la base de datos.';
                exit;
            } finally {
                $conn = null;
            }
        ?>
        <div class="container">
            <?php require __DIR__ . '/_header.php'; ?>
            <br>
            <center>
            <h2>Análisis de datos reportados</h2>
            <br>
            <p><!-- <b>Datos:</b> -->Ofertas de trabajo publicadas en la sección <a href="https://simo-ppal.cnsc.gov.co/#ofertaEmpleo">#ofertaEmpleo</a> de la plataforma <a href="https://simo-ppal.cnsc.gov.co">SIMO</a>.</p>
            <p>
            <!-- Total de ofertas<sup><a href="#fn1" id="ref1">1</a></sup>: <?php echo $total;?><br> -->
            &bull; Número de ofertas con cierre de inscripción <b>vigente y no nulo</b>:<br><b><?php echo $vigentes;?></b><br>
            &bull; Número de ofertas de empleo con cierre de inscripciones <b>por definir</b> y cuya fecha de<br>creación en la plataforma SIMO es posterior al <?php echo $year_ago ?>:<br><b><?php echo $por_definir;?></b><br>
<hr></hr>
        <!-- <sup id="fn1">1. Cada oferta se identifica por su código <a href="https://simo.cnsc.gov.co/cnscwiki/doku.php?id=simo:documentos:manual_ciudadano#mis_empleos">OPEC</a> y puede tener más de una vacante.  Las ofertas con fechas de inscripción vencidas o con cero número de vacantes no son incluidas en el análisis.<a href="#ref1" title="Jump back to footnote 1 in the text.">↩</a></sup> -->
<!-- Las ofertas sin número de vacantes reportado no son incluidas en el análisis. -->
            </center>
            </p>
        </div>
    </body>
</html>
