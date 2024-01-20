<?php
    // File to test the PHP extension: PDO MYSQL AND mysqli
    // https://www.php.net/manual/en/ref.pdo-mysql.php

    // PHP Connect to MySQL
    // https://www.w3schools.com/php/php_mysql_connect.asp

    $cnf = parse_ini_file("private/client_config.sh"); // INI format similar to SH
    $servername = $cnf["SERVER"];
    $username = $cnf["USER"];
    $password = $cnf["PASSWORD"];
    $dbname = $cnf["DATABASE"];

    // MySQLi Procedural
    // create connection
    /*
    $conn = mysqli_connect($servername, $username, $password);

    // check connection
    if (! $conn) {
        die("Connection failed: " . mysqli_connect_error());
    echo "Connected successfully";
    }
     */

    // Highly compatible alternative...
    // PDO
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected successfully";
        $conn = null;
    } catch(PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
    }
?>
