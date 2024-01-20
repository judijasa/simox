<?php

require '../private/utils/connectivity.php'

echo "<table style='border: solid 1px black;'>";
echo "<tr><th>Página</th><th>Nivel</th><th>Denominación</th><th>Grado</th><th>Código</th><th>OPEC</th><th><font color='#FFFFFF'>ss</font>Salario<font color='#FFFFFF'>ss</font></th><th>Convocatoria</th><th>Cierre de inscripciones</th><th>Número de vacantes</th><th>Estudio</th><th>Palabras clave</th><th>Dependencia</th><th>Municipio</th><th>Departamento</th></tr>";

class TableRows extends RecursiveIteratorIterator {
    function __construct($it) {
        parent::__construct($it, self::LEAVES_ONLY);
    }

    function current() {
        return "<td style='width:150px;border:1px solid black;'>" . parent::current(). "</td>";
    }

    function beginChildren() {
        echo "<tr>";
    }

    function endChildren() {
        echo "</tr>" . "\n";
    }
}

try {
    $dbname="simo_express";
    $conn = new clientPDO($dbname);
    $stmt = $conn->prepare(<<<EOD
        SELECT
            nivel AS `Nivel`,
            denomicancion AS `Denominación`,
            grado AS `Grado`,
            codigo AS `Código`,
            opec AS `OPEC`,
            salario AS `Salario`,
            convocatoria AS `Convocatoria`,
            cierre AS `Cierre`,
            vacantes AS `Vacantes`,
            estudio AS `Estudio`,
            dependencia AS `Dependencia`,
            municipio AS `Municipio`,
            departamento AS `Departamento`
        FROM job_offer;
    EOD);
    $stmt->execute();

    // set the resulting array to associative
    $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
    foreach(new TableRows(new RecursiveArrayIterator($stmt->fetchAll())) as $k=>$v) {
        echo $v;
    }
    $conn = null;
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
echo "</table>";
?>
