<?php

    // Fetch ext args:
    parse_str($argv[2],$ext_args);

    $exec_time = $ext_args['time'];
    $run = $ext_args['run'];
    $run_max = $ext_args['run_max'];
    $former_last = (int) $ext_args['ex_last'];
    $former_last_add_1 = $former_last + 1;
    $last = $ext_args['last'];
    $end = $ext_args['end'];

    $status="";
    if($last == $end){
        $status="Download complete!!";
    }

    if($last == 0){
        $former_last_add_1 = 0;
    }

    // MySQL...

    $cnf = parse_ini_file("src/config.sh"); // INI format similar to SH
    $servername = $cnf["SERVER"];
    $username = $cnf["USER"];
    $password = $cnf["PASSWORD"];
    $dbname = $cnf["DATABASE"];

    try {
        $conn = new PDO("mysql:host=$servername;port=3306;dbname=$dbname", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //echo "Connected successfully";
        // SQL Query
        // columns with spacings: www.tutorialspoint.com/how-to-select-a-column-name-with-spaces-in-mysql

        $conn->exec("INSERT INTO activity_monitor (evento) VALUES ('\n  Programmed execution of get_jobs.php ($run/$run_max)\n  Downloaded pages: From $former_last_add_1 to $last (Max. $end)\n  Lasting time of execution (HH:MM:SS): $exec_time\n  $status')");

        return true;
    } catch(PDOException $e) {
        echo "Error: " . "<br>" . $e->getMessage();
    }
    $conn = null;
?>
