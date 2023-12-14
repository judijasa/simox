
BUG IN PHP Simple HTML DOM Parser

  The error `Compilation failed: invalid range in character class at offset...`
  can appear if using PHP versions > 7.3. You will have to escape hypens or use
  an older PHP version e.g.

    preg_match('/[\w-.]+/', ''); // this will not work in PHP7.3
    preg_match('/[\w\-.]+/', ''); // the hyphen need to be escaped

BUG IN PHPCasperJS

  If `phpcasperjs --version` returns `Auto configuration failed`
  then try `OPENSSL_CONF=dev/null phpcasperjs --version`.
  In other words, you will have to change the env var OPENSSL_CONF
  accordingly to run the script.

---

CasperJS requires PhantomJS

casper-php is a wrapper of casper-js
casper-js is a wrapper of phantom-js
phantom-js is an executable and cannot be modified

---

MANAGE MYSQL USER PRIVILEGES

0) Create an `admin` user with read and write privileges for the specific database of SIMO project.    Prefereably not the root user because an admin superuser is an unnecessary risk.

1) Create a mysql user with read only privileges.  Use it in client_config.sh, file to be required in index.html 

Set the permission for client config.sh to be read only by anyone.

2) Set a mysql user with root privileges. Use it in admin_config.sh, file to be required in scripts with create/drop/update.

To create new read-only user in mysql server, access to mysql as root:

$ sudo mysql -u root

Then exec (the host '127.0.0.1' can be changed. For any host use '%')

mysql> CREATE USER 'joe'@'127.0.0.1' IDENTIFIED BY 'joe-user-password'; 
mysql> GRANT SELECT ON mydatabase.* TO 'joe'@'127.0.0.1'; 
mysql> FLUSH PRIVILEGES;
