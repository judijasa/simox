<?php
    // File to test the PHP extension: PDO MYSQL AND mysqli
    // https://www.php.net/manual/en/ref.pdo-mysql.php

    // PHP Connect to MySQL
    // https://www.w3schools.com/php/php_mysql_connect.asp

class adminPDO extends PDO {
    public function __construct($dbname) {
        $cnf = parse_ini_file("private/client_config.sh"); // INI format similar to SH
        $servername = $cnf["SERVER"];
        $username = $cnf["USER"];
        $password = $cnf["PASSWORD"];
        parent::__construct("mysql:host=$servername;dbname=$dbname", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
}
?>
