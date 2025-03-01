Access Mariadb command-line client from terminal:
    $ mariadb
    $ sudo mariadb
    $ mariadb -u [username] -p
    $ mariadb -u [username] -p < [query].sql
    $ maridab -u [username] -p [db_name]
    $ mariadb -u [username] -p -e SHOW DATABASES;

Some basic commands in the the Mariadb CLI:
    
    MariaDB [(none)]> SHOW DATABASE;

    Show current DB:
    MariaDB [(none)]> SELECT DATABASE();

    MariaDB [(none)]> USE [db_name];

    MariaDB [(none)]> SHOW TABLES;
    MariaDB [(none)]> \dt;
