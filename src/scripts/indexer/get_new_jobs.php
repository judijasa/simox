<?php
    /*
    Name: get_new_jobs.php (old: sampling_new_jobs.php)
    Description: Generate a sample of OPEC values from available job offers
     */
    require 'src/utils/connectivity.php';

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

    //************************************
    // Compare tables in MySQL:
    // www.mysqltutorial.org/compare-two-tables-to-find-unmatched-records-mysql.aspx
    //************************************

    try {
        $dbname='simo';
        $conn = new adminPDO($dbname);

        $sql = <<<SQL
        SELECT
            count(*),
            cierre
        FROM job_offer
        WHERE id > (
            SELECT value FROM cursorseq WHERE `key` = 'update_job_offer_id_seq'
        ) ORDER BY cierre;
        SQL;
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $var_1 = $stmt->fetchAll();


        $sql = <<<SQL
        SELECT
            opec,
            cierre
        FROM job_offer
        WHERE id > (
            SELECT value FROM cursorseq WHERE key='max_old_job_offer_id_seq'
        ) ORDER BY cierre, opec LIMIT 5;
        SQL; // TODO remove LMIT after test
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $var_2 = $stmt->fetchAll();

        $sql = <<<SQL
        UPDATE cursorseq SET value = (
            SELECT max(id) FROM job_offer;
        ) WHERE key = 'get_new_jobs_job_offer_id_seq';
        SQL;
        $stmt = $conn->prepare($sql);
        $stmt->execute();

        echo count($var_2). " <b>new jobs</b> added after recent download.";

        //**********************************
        // 1st Table: # de empleos por cada
        // cierre de inscripiciones
        //**********************************

        echo "<br><table style='border: solid 1px black;'>";
        echo "<caption>New jobs added, grouped by deadline</caption>";
        echo "<tr><th>Cierre de inscripciones</th><th>Número de Empleos</th></tr>";


        foreach(new TableRows(new RecursiveArrayIterator($var_1)) as $k=>$v) {
            echo $v;
        }
        echo "</table>";

        //**********************************
        // 2nd Table: Some new Jobs by OPEC
        //**********************************

        // Table might be too big for
        // email hence limit shown rows:
        $rowmax = 10;

        echo "<br><table style='border: solid 1px black;'>";
        echo "<caption>First ". $rowmax. " new jobs added, ordered by OPEC</caption>";
        echo "<tr><th>OPEC</th><th>Cierre de inscripciones</th></tr>";

        foreach(new TableRows(new RecursiveArrayIterator(array_slice($var_2,0,$rowmax))) as $k=>$v) {
            echo $v;
        }
        echo "</table>";

    } catch(PDOException $e) {
        echo "Error: " . "<br>" . $e->getMessage();
    } finally {
        $conn = null;
    }
?>
