<?php

require 'vendor/autoload.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Connectivity\Database;
use Utils\DatabaseOps\BatchScan;

function insert_convocatorias(PDO $conn, array $rows): void
{
    $sql = 'INSERT INTO convocatoria (code, codigo, nombre, agno)
            VALUES (:code, :codigo, :nombre, :agno)
            ON DUPLICATE KEY UPDATE id = id';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        $conv = $empleo['convocatoria'] ?? null;
        if ($conv === null) continue;
        $stmt->execute([':code' => $conv['id'], ':codigo' => $conv['codigo'], ':nombre' => $conv['nombre'], ':agno' => $conv['agno']]);
    }
}

function insert_denominaciones(PDO $conn, array $rows): void
{
    $sql = 'INSERT INTO denominacion (code, nivel, nombre)
            VALUES (:code, :nivel, :nombre)
            ON DUPLICATE KEY UPDATE id = id';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        $den = $empleo['denominacion'] ?? null;
        if ($den === null || $den['id'] === null) continue;
        $stmt->execute([':code' => $den['id'], ':nivel' => json_encode($den['nivel'] ?? null), ':nombre' => $den['nombre']]);
    }
}

function insert_dependencias(PDO $conn, array $rows): void
{
    $sql = 'INSERT INTO dependencia (code, nombre)
            VALUES (:code, :nombre)
            ON DUPLICATE KEY UPDATE id = id';
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
    $sql = 'INSERT INTO municipio (code, nombre, departamento)
            VALUES (:code, :nombre, :departamento)
            ON DUPLICATE KEY UPDATE id = id';
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
    $sql = 'INSERT INTO requisito (code, estudio, experiencia, otros, alternativas, equivalencias)
            VALUES (:code, :estudio, :experiencia, :otros, :alternativas, :equivalencias)
            ON DUPLICATE KEY UPDATE id = id';
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

function insert_funciones(PDO $conn, array $rows): void
{
    $sql = 'INSERT INTO funcion (code, descripcion)
            VALUES (:code, :descripcion)
            ON DUPLICATE KEY UPDATE id = id';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        foreach ($empleo['funciones'] ?? [] as $funcion) {
            $stmt->execute([':code' => $funcion['id'], ':descripcion' => $funcion['descripcion']]);
        }
    }
}

function insert_documentos(PDO $conn, array $rows): void
{
    $sql = 'INSERT INTO documento (code, ruta_archivo, content_type, version, stage_id, fecha, documento_origen_id)
            VALUES (:code, :ruta_archivo, :content_type, :version, :stage_id, :fecha, :documento_origen_id)
            ON DUPLICATE KEY UPDATE id = id';
    $stmt = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);
        $doc = $empleo['documento'] ?? null;
        if ($doc === null) continue;
        $stmt->execute([
            ':code'                => $doc['id'],
            ':ruta_archivo'        => $doc['rutaArchivo'] ?? null,
            ':content_type'        => $doc['contentType'] ?? null,
            ':version'             => $doc['version'] ?? null,
            ':stage_id'            => $doc['stageId'] ?? null,
            ':fecha'               => $doc['fecha'] ?? null,
            ':documento_origen_id' => $doc['documentoOrigenId'] ?? null,
        ]);
    }
}

function insert_vacantes(PDO $conn, array $rows): void
{
    $sql_mun = 'SELECT id FROM municipio WHERE code = :code LIMIT 1';
    $sql_dep = 'SELECT id FROM dependencia WHERE code = :code LIMIT 1';
    $sql = 'INSERT INTO vacante
                (code, cantidad_ascensos, municipio, municipio_id, dependencia, dependencia_id,
                 fecha_generada, cantidad, disponible, cargos_vacantes, ocupadas_pre_pensionados)
            VALUES
                (:code, :cantidad_ascensos, :municipio, :municipio_id, :dependencia, :dependencia_id,
                 :fecha_generada, :cantidad, :disponible, :cargos_vacantes, :ocupadas_pre_pensionados)
            ON DUPLICATE KEY UPDATE id = id';
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
                ':municipio'               => json_encode($vac['municipio'] ?? null),
                ':municipio_id'            => $municipio_id,
                ':dependencia'             => json_encode($vac['dependencia'] ?? null),
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

function insert_empleo(PDO $conn, array $rows): void
{
    $sql_den  = 'SELECT id FROM denominacion WHERE code = :code LIMIT 1';
    $sql_conv = 'SELECT id FROM convocatoria WHERE code = :code LIMIT 1';
    $sql_doc  = 'SELECT id FROM documento WHERE code = :code LIMIT 1';
    $sql = 'INSERT INTO empleo
                (opec, created_date, asignacion_salarial, codigo_empleo, sin_codigo,
                 denominacion_id, grado_nivel, grado_denominacion, convocatoria_id,
                 area, discapacidades, documento_id, entidad, identificador,
                 vigencia_salarial, urbano, aeronautico, no_cobro_opec,
                 estado_inscripcion, favorito, inscripcion_id, fecha_inscripcion,
                 nivel_nombre, `access`)
            VALUES
                (:opec, :created_date, :asignacion_salarial, :codigo_empleo, :sin_codigo,
                 :denominacion_id, :grado_nivel, :grado_denominacion, :convocatoria_id,
                 :area, :discapacidades, :documento_id, :entidad, :identificador,
                 :vigencia_salarial, :urbano, :aeronautico, :no_cobro_opec,
                 :estado_inscripcion, :favorito, :inscripcion_id, :fecha_inscripcion,
                 :nivel_nombre, :access)
            ON DUPLICATE KEY UPDATE id = id';
    $den_lookup  = $conn->prepare($sql_den);
    $conv_lookup = $conn->prepare($sql_conv);
    $doc_lookup  = $conn->prepare($sql_doc);
    $stmt        = $conn->prepare($sql);
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);

        $den_lookup->execute([':code' => $empleo['denominacion']['id'] ?? null]);
        $denominacion_id = $den_lookup->fetchColumn() ?: null;

        $conv_lookup->execute([':code' => $empleo['convocatoria']['id'] ?? null]);
        $convocatoria_id = $conv_lookup->fetchColumn() ?: null;

        $doc_lookup->execute([':code' => $empleo['documento']['id'] ?? null]);
        $documento_id = $doc_lookup->fetchColumn() ?: null;

        $stmt->execute([
            ':opec'                => $empleo['id'],
            ':created_date'        => $empleo['createdDate'] ?? null,
            ':asignacion_salarial' => $empleo['asignacionSalarial'] ?? null,
            ':codigo_empleo'       => $empleo['codigoEmpleo'] ?? null,
            ':sin_codigo'          => (int)$empleo['sinCodigo'] ?? null,
            ':denominacion_id'     => $denominacion_id,
            ':grado_nivel'         => json_encode($empleo['gradoNivel'] ?? null),
            ':grado_denominacion'  => json_encode($empleo['gradoDenominacion'] ?? null),
            ':convocatoria_id'     => $convocatoria_id,
            ':area'                => json_encode($empleo['area'] ?? null),
            ':discapacidades'      => json_encode($empleo['discapacidades'] ?? []),
            ':documento_id'        => $documento_id,
            ':entidad'             => json_encode($empleo['entidad'] ?? null),
            ':identificador'       => $empleo['identificador'] ?? null,
            ':vigencia_salarial'   => $empleo['vigenciaSalarial'] ?? null,
            ':urbano'              => (int)$empleo['urbano'] ?? null,
            ':aeronautico'         => (int)$empleo['aeronautico'] ?? null,
            ':no_cobro_opec'       => (int)$empleo['noCobroOpec'] ?? null,
            ':estado_inscripcion'  => $row['estado_inscripcion'],
            ':favorito'            => $row['favorito'],
            ':inscripcion_id'      => $row['inscripcion_id'],
            ':fecha_inscripcion'   => $row['fecha_inscripcion'],
            ':nivel_nombre'        => $row['nivel_nombre'],
            ':access'              => $row['access'],
        ]);
    }
}

function process_batch(PDO $conn, array $rows): void
{
    insert_convocatorias($conn, $rows);
    insert_dependencias($conn, $rows);
    insert_municipios($conn, $rows);
    insert_requisitos($conn, $rows);
    insert_denominaciones($conn, $rows);
    insert_funciones($conn, $rows);
    insert_documentos($conn, $rows);
    insert_vacantes($conn, $rows);
    insert_empleo($conn, $rows);
}

#[CronJob(schedule: 'daily')]
#[Agent]
function main(): void
{
    $conn = Database::admin('simo');

    $table_name = 'empleo_snapshot';
    $query = 'SELECT id, empleo, estado_inscripcion, favorito, inscripcion_id,
                     fecha_inscripcion, nivel_nombre, `access`
              FROM empleo_snapshot
              WHERE id >= :curr_id AND id < :next_id
                AND ABS(id) % :div = :mod';
    $batch_size = 500;
    $cursor_key= 'pipeline_main';

    BatchScan::scan(
        $conn,
        $table_name,
        $query,
        $batch_size,
        $cursor_key,
        fn(array $rows) => process_batch($conn, $rows),
    );
}

