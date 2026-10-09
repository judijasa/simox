<!DOCTYPE html>
<html>

<!--
Content: table of contents
Each card links to its full table page.
-->

    <head>
        <title>SimoEx — índice</title>
        <meta name="viewport" charset="utf-8" content="width=device-width, initial-scale=1">
        <link rel="shortcut icon" href="favicon.ico">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
            crossorigin="anonymous">
        <link rel="stylesheet" type="text/css" href="mystyle.css">
        <style>
            .toc-card {
                display: block;
                border: 1px solid #ccc;
                background: #fafafa;
                padding: 16px;
                margin-bottom: 16px;
                text-decoration: none;
                color: #333;
            }
            .toc-card:hover {
                background: #eef6fb;
                border-color: #08c;
            }
            .toc-card h2 { margin: 0 0 8px; font-size: 20px; color: #08c; }
            .toc-card p { margin: 0 0 8px; color: #555; }
            .toc-card .go { color: #08c; font-weight: bold; }
        </style>
    </head>
    <body>
        <?php $hace_un_anio = date('Y-m-d', strtotime('-1 year')); ?>

        <div class="container">
            <?php require __DIR__ . '/_header.php'; ?>

            <a class="toc-card" href="sin_cierre.php">
                <h2>Empleos con cierre de inscripciones por definir</h2>
                <p>Ofertas de empleo con cierre de inscripciones <b>por definir</b> y cuya fecha de creación en la plataforma SIMO es posterior al <b><?php echo $hace_un_anio; ?></b>.</p>
                <span class="go">Abrir &rarr;</span>
            </a>

            <a class="toc-card" href="con_cierre.php">
                <h2>Empleos con cierre de inscripciones definido</h2>
                <p>Ofertas de empleo cuyo cierre de inscripciones es posterior al <b><?php echo $hace_un_anio; ?></b>.</p>
                <span class="go">Abrir &rarr;</span>
            </a>
        </div>
    </body>
</html>
