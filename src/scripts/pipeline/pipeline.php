<?php

require 'vendor/autoload.php';
require 'src/utils/DatabaseOps/BatchScan.php';

use Utils\Connectivity\Database;
use Utils\DatabaseOps;

function insert_niveles(PDOStatement $stmt, array $rows): void
{
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        $nivel = $empleo['denominacion']['nivel'] ?? null;
        if ($nivel === null) continue;
        $stmt->execute([':code' => $nivel['id'], ':nombre' => $nivel['nombre']]);
    }
}

function insert_denominaciones(PDOStatement $insert, PDOStatement $nivel_lookup, array $rows): void
{
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        $den = $empleo['denominacion'] ?? null;
        if ($den === null || $den['id'] === null) continue;

        $nivel = $den['nivel'] ?? null;
        $nivel_lookup->execute([
            ':code'   => $nivel['id'] ?? null,
            ':nombre' => $nivel['nombre'] ?? $row['nivel_nombre'],
        ]);
        $nivel_id = $nivel_lookup->fetchColumn() ?: null;

        $insert->execute([
            ':code'     => $den['id'],
            ':nivel_id' => $nivel_id,
            ':nombre'   => $den['nombre'],
        ]);
    }
}

function process_batch(PDO $conn, array $rows): void
{
    $nivel_insert = $conn->prepare(<<<EOD
        INSERT INTO nivel (code, nombre) VALUES (:code, :nombre)
        ON DUPLICATE KEY UPDATE code = COALESCE(code, VALUES(code))
        EOD);

    $nivel_lookup = $conn->prepare(<<<EOD
        SELECT id FROM nivel
        WHERE code = :code OR nombre = :nombre
        LIMIT 1
        EOD);

    $den_insert = $conn->prepare(<<<EOD
        INSERT IGNORE INTO denominacion (code, nivel_id, nombre)
        VALUES (:code, :nivel_id, :nombre)
        EOD);

    insert_niveles($nivel_insert, $rows);
    insert_denominaciones($den_insert, $nivel_lookup, $rows);
}

function main(): void
{
    $conn = Database::admin('simo');

    $query = <<<EOD
        SELECT id, empleo, nivel_nombre
        FROM empleo_snapshot
        WHERE id >= :curr_id AND id < :next_id
          AND ABS(id) % :div = :mod
        EOD;

    DatabaseOps\scan_table_in_batches(
        $conn,
        'empleo_snapshot',
        $query,
        500,
        'pipeline_main',
        fn(array $rows) => process_batch($conn, $rows),
    );
}

main();
