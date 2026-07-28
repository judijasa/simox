<?php

declare(strict_types=1);

namespace Utils\Connectivity;

use PDO;

class Database extends PDO
{
    private function __construct(string $dsn, string $user, string $pass, array $options = []) {
        parent::__construct($dsn, $user, $pass, $options);
    }

    private static function loadConfig(): array {
        $target = getenv('EMA_TARGET') ?: 'local';
        $path = __DIR__ . '/../../../etc/reuter.ini';
        $cnf = parse_ini_file($path, true);
        if ($cnf === false || !isset($cnf[$target])) {
            throw new \RuntimeException("Target '$target' not found in $path");
        }
        return $cnf[$target];
    }

    private static function buildDsn(string $dbname, array $cnf): string {
        $server = $cnf['SERVER'];
        $port = $cnf['PORT'] ?? '3306';

        $dsn = "mysql:host={$server};port={$port};dbname={$dbname};charset=utf8mb4";

        $socket = getenv('MYSQL_UNIX_PORT');
        if ($socket !== false && $socket !== '') {
            $dsn .= ';unix_socket=' . $socket;
        }
        return $dsn;
    }

    private static function baseOptions(): array {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    public static function admin(string $dbname): self {
        $cnf = self::loadConfig();
        return new self(
            self::buildDsn($dbname, $cnf),
            'admin',
            $cnf['ADMIN_PASSWORD'],
            self::baseOptions()
        );
    }

    public static function reader(string $dbname): self {
        $cnf = self::loadConfig();
        return new self(
            self::buildDsn($dbname, $cnf),
            'reader',
            $cnf['READER_PASSWORD'],
            self::baseOptions()
        );
    }

    public static function public(string $dbname): self {
        $cnf = self::loadConfig();
        return new self(
            self::buildDsn($dbname, $cnf),
            'public',
            '',
            self::baseOptions()
        );
    }
}
