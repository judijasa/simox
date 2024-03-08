<?php

    // PHP Connect to MySQL
    // https://www.w3schools.com/php/php_mysql_connect.asp

    // PDO-MYSQL
    // https://www.php.net/manual/en/ref.pdo-mysql.php

class publicPDO extends PDO {
    public function __construct($dbname) {
	    $cnf = parse_ini_file("/var/www/html/simo-express/config.sh");
        $servername = $cnf["SERVER"];
        $username = 'public';
        $password = '';
        parent::__construct("mysql:host=$servername;dbname=$dbname", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }
}

function publicMySQLi($dbname){
    // Not executed from repo's root dir: Use abs path...
    $cnf = parse_ini_file("/var/www/html/simo-express/config.sh");
    $servername = $cnf["SERVER"];
    $username = 'public';
    $password = '';
    $conn = mysqli_connect($servername, $username, $password);
    if (! $conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    else {
        mysqli_select_db($conn, $dbname);
        return $conn;
    }
}
?>
