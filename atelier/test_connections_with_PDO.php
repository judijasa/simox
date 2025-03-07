<?php
// Test PDO children classes

    require 'src/utils/connectivity.php';

    try {
        $dbname = 'simo';
        $conn = new adminPDO($dbname);
        echo "Connected successfully with adminPDO". PHP_EOL;
    } catch(PDOException $e) {
        echo "Connection failed: ". $e->getMessage(). PHP_EOL;
    } finally {
        $conn = null;
    }


    try {
        $dbname = 'simo';
        $conn = new readerPDO($dbname);
        echo "Connected successfully with readerPDO". PHP_EOL;
    } catch(PDOException $e) {
        echo "Connection failed: ". $e->getMessage(). PHP_EOL;
    } finally {
        $conn = null;
    }

    try {
        $dbname = 'simo';
        $conn = new publicPDO($dbname);
        echo "Connected successfully with publicPDO". PHP_EOL;
    } catch(PDOException $e) {
        echo "Connection failed: ". $e->getMessage(). PHP_EOL;
    } finally {
        $conn = null;
    }

    try {
        $dbname = 'simo';
        $conn = publicMySQLi($dbname);
        echo "Connected successfully with publicMySQLi". PHP_EOL;
    } catch(PDOException $e) {
        echo "Connection failed: ". $e->getMessage(). PHP_EOL;
    } finally {
        $conn = null;
    }
?>
