<?php
    // File to test the PHP extension: PDO MYSQL AND mysqli
    // https://www.php.net/manual/en/ref.pdo-mysql.php

    // PHP Connect to MySQL
    // https://www.w3schools.com/php/php_mysql_connect.asp

    $cnf = parse_ini_file("src/config.sh"); // INI format similar to SH
    $servername = $cnf["SERVER"];
    $username = 'public';
    $password = '';
    $dbname = $cnf["DBNAME"];

    // MySQLi driver (using its procedure style instead of its object oriented style)

    $conn = mysqli_connect($servername, $username, $password);

    // check connection
    if (mysqli_connect_errno()) {
      die("Connection failed: " . mysqli_connect_error());
    } else {
      echo "Connected successfully via mysqli_connect.". PHP_EOL;
      mysqli_close($conn);
    }

    // Highly compatible alternative... (recommended)
    // MySQL PDO driver (using its object oriented style)
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected successfully via MySQL PDO.". PHP_EOL;
        $conn = null;
    } catch(PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
    }
?>
