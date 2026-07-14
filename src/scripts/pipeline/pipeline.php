<?php

require 'vendor/autoload.php';
use Utils\Connectivity\Database;
use Utils\DatabaseOps\BatchScan;

function insert_niveles(PDO $conn, array $rows): void
{
    $sql = 'INSERT IGNORE INTO nivel (code, nombre) VALUES (:code, :nombre)';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        $nivel = $empleo['denominacion']['nivel'] ?? null;
        if ($nivel === null) continue;
        $stmt->execute([':code' => $nivel['id'], ':nombre' => $nivel['nombre']]);
    }
}

function insert_convocatorias(PDO $conn, array $rows): void
{
    $sql = 'INSERT IGNORE INTO convocatoria (codigo, nombre, agno) VALUES (:codigo, :nombre, :agno)';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        $conv = $empleo['convocatoria'] ?? null;
        if ($conv === null) continue;
        $stmt->execute([':codigo' => $conv['codigo'], ':nombre' => $conv['nombre'], ':agno' => $conv['agno']]);
    }
}

function insert_denominaciones(PDO $conn, array $rows): void
{
    $sql_lookup = 'SELECT id FROM nivel WHERE code = :code OR nombre = :nombre LIMIT 1';
    $sql = 'INSERT IGNORE INTO denominacion (code, nivel_id, nombre) VALUES (:code, :nivel_id, :nombre)';
    $lookup = $conn->prepare($sql_lookup);
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        $den = $empleo['denominacion'] ?? null;
        if ($den === null || $den['id'] === null) continue;
        $nivel = $den['nivel'] ?? null;
        $lookup->execute([
            ':code'   => $nivel['id'] ?? null,
            ':nombre' => $nivel['nombre'] ?? $row['nivel_nombre'],
        ]);
        $nivel_id = $lookup->fetchColumn() ?: null;
        $stmt->execute([':code' => $den['id'], ':nivel_id' => $nivel_id, ':nombre' => $den['nombre']]);
    }
}

function insert_dependencias(PDO $conn, array $rows): void
{
    $sql = 'INSERT IGNORE INTO dependencia (code, nombre) VALUES (:code, :nombre)';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        foreach ($empleo['vacantes'] ?? [] as $vacante) {
            $dep = $vacante['dependencia'] ?? null;
            if ($dep === null) continue;
            $stmt->execute([':code' => $dep['id'], ':nombre' => $dep['nombre']]);
        }
    }
}

function insert_municipios(PDO $conn, array $rows): void
{
    $sql = 'INSERT IGNORE INTO municipio (code, nombre, departamento) VALUES (:code, :nombre, :departamento)';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        foreach ($empleo['vacantes'] ?? [] as $vacante) {
            $mun = $vacante['municipio'] ?? null;
            if ($mun === null) continue;
            $stmt->execute([
                ':code'        => $mun['id'],
                ':nombre'      => $mun['nombre'],
                ':departamento' => $mun['departamento']['nombre'] ?? null,
            ]);
        }
    }
}

function insert_requisitos(PDO $conn, array $rows): void
{
    $sql = 'INSERT IGNORE INTO requisito (code, estudio, experiencia, otros, alternativas, equivalencias)
            VALUES (:code, :estudio, :experiencia, :otros, :alternativas, :equivalencias)';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        foreach ($empleo['requisitosMinimos'] ?? [] as $req) {
            $stmt->execute([
                ':code'         => $req['id'],
                ':estudio'      => $req['estudio'] ?? null,
                ':experiencia'  => $req['experiencia'] ?? null,
                ':otros'        => $req['otros'] ?? null,
                ':alternativas' => json_encode($req['alternativas'] ?? []),
                ':equivalencias'=> json_encode($req['equivalencias'] ?? []),
            ]);
        }
    }
}

function insert_vacantes(PDO $conn, array $rows): void
{
    $sql_mun = 'SELECT id FROM municipio WHERE code = :code LIMIT 1';
    $sql_dep = 'SELECT id FROM dependencia WHERE code = :code LIMIT 1';
    $sql = 'INSERT IGNORE INTO vacante
                (code, cantidad_ascensos, municipio_id, dependencia_id,
                 fecha_generada, cantidad, disponible, cargos_vacantes, ocupadas_pre_pensionados)
            VALUES
                (:code, :cantidad_ascensos, :municipio_id, :dependencia_id,
                 :fecha_generada, :cantidad, :disponible, :cargos_vacantes, :ocupadas_pre_pensionados)';
    $mun_lookup = $conn->prepare($sql_mun);
    $dep_lookup = $conn->prepare($sql_dep);
    $stmt       = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        foreach ($empleo['vacantes'] ?? [] as $vac) {
            $mun_lookup->execute([':code' => $vac['municipio']['id'] ?? null]);
            $municipio_id = $mun_lookup->fetchColumn() ?: null;

            $dep_lookup->execute([':code' => $vac['dependencia']['id'] ?? null]);
            $dependencia_id = $dep_lookup->fetchColumn() ?: null;

            $stmt->execute([
                ':code'                    => $vac['id'],
                ':cantidad_ascensos'       => $vac['cantidadAscensos'] ?? null,
                ':municipio_id'            => $municipio_id,
                ':dependencia_id'          => $dependencia_id,
                ':fecha_generada'          => $vac['fechaGenerada'] ?? null,
                ':cantidad'                => $vac['cantidad'] ?? null,
                ':disponible'              => $vac['disponible'] ?? null,
                ':cargos_vacantes'         => json_encode($vac['cargosVacantes'] ?? []),
                ':ocupadas_pre_pensionados'=> $vac['ocupadasPrePensionados'] ?? null,
            ]);
        }
    }
}

function process_batch(PDO $conn, array $rows): void
{
    insert_niveles($conn, $rows);
    insert_convocatorias($conn, $rows);
    insert_dependencias($conn, $rows);
    insert_municipios($conn, $rows);
    insert_requisitos($conn, $rows);
    insert_denominaciones($conn, $rows);
    insert_vacantes($conn, $rows);
}

function main(): void
{
    $conn = Database::admin('simo');

    $query = 'SELECT id, empleo, nivel_nombre
              FROM empleo_snapshot
              WHERE id >= :curr_id AND id < :next_id
                AND ABS(id) % :div = :mod';

    BatchScan::scan(
        $conn,
        'empleo_snapshot',
        $query,
        500,
        'pipeline_main',
        fn(array $rows) => process_batch($conn, $rows),
    );
}

main();
