<?php

    // PHP Connect to MySQL
    // https://www.w3schools.com/php/php_mysql_connect.asp

    // PDO-MYSQL
    // https://www.php.net/manual/en/ref.pdo-mysql.php


class adminPDO extends PDO {
    public function __construct($dbname) {
        $cnf = parse_ini_file("src/config.sh"); // INI format similar to SH
        $servername = $cnf["SERVER"];
        $username = 'admin';
        $password = $cnf["ADMIN_PASSWORD"];
        parent::__construct("mysql:host=$servername;dbname=$dbname", "$username", "$password", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
}


class readerPDO extends PDO {
    public function __construct($dbname) {
        $cnf = parse_ini_file("src/config.sh"); // INI format similar to SH
        $servername = $cnf["SERVER"];
        $username = 'reader';
        $password = $cnf["READER_PASSWORD"];
        parent::__construct("mysql:host=$servername;dbname=$dbname", "$username", "$password", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
}


class publicPDO extends PDO {
    public function __construct($dbname) {
        $cnf = parse_ini_file("src/config.sh"); // INI format similar to SH
        $servername = $cnf["SERVER"];
        $username = 'public';
        $password = '';
        parent::__construct("mysql:host=$servername;dbname=$dbname", "$username", "$password", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
}

function publicMySQLi($dbname){
    // Not executed from repo's root dir: Use abs path...
    $cnf = parse_ini_file("/srv/git/SIMOExpress/src/config.sh");
    $servername = $cnf["SERVER"];
    $username = 'public';
    $password = '';
    $conn = mysqli_connect($servername, "$username", "$password");
    if (! $conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    else {
        mysqli_select_db($conn, $dbname);
        return $conn;
    }
}
?>
