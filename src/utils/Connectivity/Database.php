<?php

declare(strict_types=1);

namespace Utils\Connectivity;

use PDO;

class Database extends PDO
{
    private function __construct(string $dsn, string $user, string $pass, array $options = []) {
        parent::__construct($dsn, $user, $pass, $options);
    }

    private static function buildDsn(string $dbname): string {
        $cnf = parse_ini_file(__DIR__. '/../../config.sh');
        $servername = $cnf["SERVER"];

        // host=... is ignored if a socket is specified
        $dns = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";

        // Inject custom socket location if set (otherwise PHP default)
        $socket_spec = '';
        $cmd = '[[ -v MYSQL_UNIX_PORT ]] && echo "true" || echo "false"';
        $is_set = trim(shell_exec($cmd));  // trim \n
        if ($is_set === "true") {
            $socket = trim(shell_exec('echo $MYSQL_UNIX_PORT'));
            $socket_spec = ';unix_socket=' . $socket;
        }
        return $dns . $socket_spec;
    }

    private static function baseOptions(): array {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    public static function admin(string $dbname): self {
        $cnf = parse_ini_file(__DIR__. '/../../config.sh');
        return new self(
            self::buildDsn($dbname),
            'admin',
            $cnf["ADMIN_PASSWORD"],
            self::baseOptions()
        );
    }

    public static function reader(string $dbname): self {
        $cnf = parse_ini_file(__DIR__. '/../../config.sh');
        return new self(
            self::buildDsn($dbname),
            'reader',
            $cnf["READER_PASSWORD"],
            self::baseOptions()
        );
    }

    public static function public(string $dbname): self {
        return new self(
            self::buildDsn($dbname),
            'public',
            '',
            self::baseOptions()
        );
    }
}

