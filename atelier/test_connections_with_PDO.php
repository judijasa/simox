<?php
require 'vendor/autoload.php';

use Utils\Connectivity\Database;

try {
    $dbname = 'simo';
    $conn = Database::admin($dbname);
    echo "Connected successfully with Database::admin". PHP_EOL;
} catch(PDOException $e) {
    echo "Connection failed: ". $e->getMessage(). PHP_EOL;
} finally {
    $conn = null;
}


try {
    $dbname = 'simo';
    $conn = Database::reader($dbname);
    echo "Connected successfully with Database::reader". PHP_EOL;
} catch(PDOException $e) {
    echo "Connection failed: ". $e->getMessage(). PHP_EOL;
} finally {
    $conn = null;
}

try {
    $dbname = 'simo';
    $conn = Database::public($dbname);
    echo "Connected successfully with Database::public". PHP_EOL;
} catch(PDOException $e) {
    echo "Connection failed: ". $e->getMessage(). PHP_EOL;
} finally {
    $conn = null;
}

# try {
    # $dbname = 'simo';
    # $conn = publicMySQLi($dbname);
    # echo "Connected successfully with publicMySQLi". PHP_EOL;
# } catch(PDOException $e) {
    # echo "Connection failed: ". $e->getMessage(). PHP_EOL;
# } finally {
#     $conn = null;
# }

