<?php

require 'vendor/autoload.php';
require_once __DIR__ . '/helper.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Connectivity\Database;
use Utils\DatabaseOps\BatchScan;
use Utils\DatabaseOps\BatchInsert;

function insert_niveles(PDO $conn, array $empleos, int $batch_size): void
{
    $niveles = [];
    foreach ($empleos as $empleo) {
        $nivel = $empleo['denominacion']['nivel'] ?? null;
        if ($nivel !== null) $niveles[] = $nivel;
    }
    $niveles = deduplicate_by($niveles, 'id');
    $rows    = array_map(fn($n) => [$n['id'], $n['nombre']], $niveles);
    BatchInsert::insert($conn, 'nivel', ['code', 'nombre'], $rows, $batch_size);
}

function insert_tipo_entidades(PDO $conn, array $empleos, int $batch_size): void
{
    $tipos = [];
    foreach ($empleos as $empleo) {
        $tipo = $empleo['convocatoria']['entidad']['tipoEntidad'] ?? null;
        if ($tipo !== null) $tipos[] = $tipo;
    }
    $tipos = deduplicate_by($tipos, 'id');
    $rows  = array_map(fn($t) => [$t['id'], $t['nombre']], $tipos);
    BatchInsert::insert($conn, 'tipo_entidad', ['code', 'nombre'], $rows, $batch_size);
}

function insert_entidades(PDO $conn, array $empleos, int $batch_size): void
{
    $lookup = $conn->prepare('SELECT id FROM tipo_entidad WHERE code = :code LIMIT 1');

    $entidades = [];
    foreach ($empleos as $empleo) {
        $entidad = $empleo['convocatoria']['entidad'] ?? null;
        if ($entidad !== null) $entidades[] = $entidad;
    }
    $entidades = deduplicate_by($entidades, 'id');

    $rows = [];
    foreach ($entidades as $entidad) {
        $lookup->execute([':code' => $entidad['tipoEntidad']['id'] ?? null]);
        $rows[] = [
            $entidad['id'],
            $entidad['nit'],
            $entidad['nombre'],
            json_encode($entidad['tipoEntidad'] ?? null),
            $lookup->fetchColumn() ?: null,
        ];
    }
    $columns = ['code', 'nit', 'nombre', 'tipo_entidad', 'tipo_entidad_id'];
    BatchInsert::insert($conn, 'entidad', $columns, $rows, $batch_size);
}

function insert_convocatorias(PDO $conn, array $empleos, int $batch_size): void
{
    $convs = [];
    foreach ($empleos as $empleo) {
        $conv = $empleo['convocatoria'] ?? null;
        if ($conv !== null) $convs[] = $conv;
    }
    $convs = deduplicate_by($convs, 'id');
    $rows  = array_map(fn($c) => [$c['id'], $c['codigo'], $c['nombre'], $c['agno']], $convs);
    $columns = ['code', 'codigo', 'nombre', 'agno'];
    BatchInsert::insert($conn, 'convocatoria', $columns, $rows, $batch_size);
}

function insert_denominaciones(PDO $conn, array $empleos, int $batch_size): void
{
    $lookup = $conn->prepare('SELECT id FROM nivel WHERE code = :code LIMIT 1');

    $dens = [];
    foreach ($empleos as $empleo) {
        $den = $empleo['denominacion'] ?? null;
        if ($den !== null && $den['id'] !== null) $dens[] = $den;
    }
    $dens = deduplicate_by($dens, 'id');

    $rows = [];
    foreach ($dens as $den) {
        $nivel = $den['nivel'] ?? null;
        $lookup->execute([':code' => $nivel['id'] ?? null]);
        $rows[] = [
            $den['id'],
            json_encode($nivel),
            $lookup->fetchColumn() ?: null,
            $den['nombre'],
        ];
    }
    $columns = ['code', 'nivel', 'nivel_id', 'nombre'];
    BatchInsert::insert($conn, 'denominacion', $columns, $rows, $batch_size);
}

function insert_dependencias(PDO $conn, array $empleos, int $batch_size): void
{
    $deps = [];
    foreach ($empleos as $empleo) {
        foreach ($empleo['vacantes'] ?? [] as $vacante) {
            $dep = $vacante['dependencia'] ?? null;
            if ($dep !== null) $deps[] = $dep;
        }
    }
    $deps = deduplicate_by($deps, 'id');
    $rows = array_map(fn($d) => [$d['id'], $d['nombre']], $deps);
    BatchInsert::insert($conn, 'dependencia', ['code', 'nombre'], $rows, $batch_size);
}

function insert_municipios(PDO $conn, array $empleos, int $batch_size): void
{
    $muns = [];
    foreach ($empleos as $empleo) {
        foreach ($empleo['vacantes'] ?? [] as $vacante) {
            $mun = $vacante['municipio'] ?? null;
            if ($mun !== null) $muns[] = $mun;
        }
    }
    $muns = deduplicate_by($muns, 'id');
    $rows = array_map(fn($m) => [$m['id'], $m['nombre'], $m['departamento']['nombre'] ?? null], $muns);
    BatchInsert::insert($conn, 'municipio', ['code', 'nombre', 'departamento'], $rows, $batch_size);
}

function insert_requisitos(PDO $conn, array $empleos, int $batch_size): void
{
    $reqs = [];
    foreach ($empleos as $empleo) {
        foreach ($empleo['requisitosMinimos'] ?? [] as $req) {
            $reqs[] = $req;
        }
    }
    $reqs = deduplicate_by($reqs, 'id');
    $rows = array_map(fn($r) => [
        $r['id'],
        $r['estudio'] ?? null,
        $r['experiencia'] ?? null,
        $r['otros'] ?? null,
        json_encode($r['alternativas'] ?? []),
        json_encode($r['equivalencias'] ?? []),
    ], $reqs);
    $columns = ['code', 'estudio', 'experiencia', 'otros', 'alternativas', 'equivalencias'];
    BatchInsert::insert($conn, 'requisito', $columns, $rows, $batch_size);
}

function insert_funciones(PDO $conn, array $empleos, int $batch_size): void
{
    $fns = [];
    foreach ($empleos as $empleo) {
        foreach ($empleo['funciones'] ?? [] as $funcion) {
            $fns[] = $funcion;
        }
    }
    $fns  = deduplicate_by($fns, 'id');
    $rows = array_map(fn($f) => [$f['id'], $f['descripcion']], $fns);
    BatchInsert::insert($conn, 'funcion', ['code', 'descripcion'], $rows, $batch_size);
}

function insert_documentos(PDO $conn, array $empleos, int $batch_size): void
{
    $docs = [];
    foreach ($empleos as $empleo) {
        $doc = $empleo['documento'] ?? null;
        if ($doc !== null) $docs[] = $doc;
    }
    $docs = deduplicate_by($docs, 'id');
    $rows = array_map(fn($d) => [
        $d['id'],
        $d['rutaArchivo'] ?? null,
        $d['contentType'] ?? null,
        $d['version'] ?? null,
        $d['stageId'] ?? null,
        $d['fecha'] ?? null,
        $d['documentoOrigenId'] ?? null,
    ], $docs);
    BatchInsert::insert($conn, 'documento', [
        'code', 'ruta_archivo', 'content_type', 'version', 'stage_id',
        'fecha', 'documento_origen_id'
    ], $rows, $batch_size);
}

function insert_vacantes(PDO $conn, array $empleos, int $batch_size): void
{
    $mun_lookup = $conn->prepare('SELECT id FROM municipio WHERE code = :code LIMIT 1');
    $dep_lookup = $conn->prepare('SELECT id FROM dependencia WHERE code = :code LIMIT 1');

    $vacs = [];
    foreach ($empleos as $empleo) {
        foreach ($empleo['vacantes'] ?? [] as $vac) {
            $vacs[] = $vac;
        }
    }
    $vacs = deduplicate_by($vacs, 'id');

    $rows = [];
    foreach ($vacs as $vac) {
        $mun_lookup->execute([':code' => $vac['municipio']['id'] ?? null]);
        $dep_lookup->execute([':code' => $vac['dependencia']['id'] ?? null]);
        $rows[] = [
            $vac['id'],
            $vac['cantidadAscensos'] ?? null,
            json_encode($vac['municipio'] ?? null),
            $mun_lookup->fetchColumn() ?: null,
            json_encode($vac['dependencia'] ?? null),
            $dep_lookup->fetchColumn() ?: null,
            $vac['fechaGenerada'] ?? null,
            $vac['cantidad'] ?? null,
            $vac['disponible'] ?? null,
            json_encode($vac['cargosVacantes'] ?? []),
            $vac['ocupadasPrePensionados'] ?? null,
        ];
    }
    BatchInsert::insert($conn, 'vacante', [
        'code', 'cantidad_ascensos', 'municipio', 'municipio_id', 'dependencia',
        'dependencia_id', 'fecha_generada', 'cantidad', 'disponible',
        'cargos_vacantes', 'ocupadas_pre_pensionados'
    ], $rows, $batch_size);
}

function insert_empleos(PDO $conn, array $rows, int $batch_size): void
{
    $den_lookup  = $conn->prepare('SELECT id FROM denominacion WHERE code = :code LIMIT 1');
    $conv_lookup = $conn->prepare('SELECT id FROM convocatoria WHERE code = :code LIMIT 1');
    $doc_lookup  = $conn->prepare('SELECT id FROM documento WHERE code = :code LIMIT 1');
    $ent_lookup  = $conn->prepare('SELECT id FROM entidad WHERE code = :code LIMIT 1');

    $insert_rows = [];
    foreach ($rows as $row) {
        $empleo = json_decode($row['empleo'], true);

        $den_lookup->execute([':code' => $empleo['denominacion']['id'] ?? null]);
        $conv_lookup->execute([':code' => $empleo['convocatoria']['id'] ?? null]);
        $doc_lookup->execute([':code' => $empleo['documento']['id'] ?? null]);
        $ent_lookup->execute([':code' => $empleo['entidad']['id'] ?? null]);

        $insert_rows[] = [
            $empleo['id'],
            $empleo['createdDate'] ?? null,
            $empleo['asignacionSalarial'] ?? null,
            $empleo['codigoEmpleo'] ?? null,
            (int)($empleo['sinCodigo'] ?? 0),
            $den_lookup->fetchColumn() ?: null,
            $empleo['descripcion'] ?? null,
            json_encode($empleo['gradoNivel'] ?? null),
            json_encode($empleo['gradoDenominacion'] ?? null),
            $conv_lookup->fetchColumn() ?: null,
            json_encode($empleo['area'] ?? null),
            json_encode($empleo['discapacidades'] ?? []),
            $doc_lookup->fetchColumn() ?: null,
            $ent_lookup->fetchColumn() ?: null,
            $empleo['identificador'] ?? null,
            $empleo['vigenciaSalarial'] ?? null,
            (int)($empleo['urbano'] ?? 0),
            (int)($empleo['aeronautico'] ?? 0),
            (int)($empleo['noCobroOpec'] ?? 0),
            $row['estado_inscripcion'],
            $row['favorito'],
            $row['inscripcion_id'],
            $row['fecha_inscripcion'],
            $row['nivel_nombre'],
            $row['access'],
        ];
    }
    BatchInsert::insert($conn, 'empleo', [
        'opec', 'created_date', 'asignacion_salarial', 'codigo_empleo', 'sin_codigo',
        'denominacion_id', 'descripcion', 'grado_nivel', 'grado_denominacion',
        'convocatoria_id', 'area', 'discapacidades', 'documento_id', 'entidad_id',
        'identificador', 'vigencia_salarial', 'urbano', 'aeronautico', 'no_cobro_opec',
        'estado_inscripcion', 'favorito', 'inscripcion_id', 'fecha_inscripcion',
        'nivel_nombre', 'access',
    ], $insert_rows, $batch_size);
}

function process_batch(PDO $conn, array $rows, int $batch_size): void
{
    $empleos = array_map(fn($row) => json_decode($row['empleo'], true), $rows);

    insert_niveles($conn, $empleos, $batch_size);
    insert_tipo_entidades($conn, $empleos, $batch_size);
    insert_entidades($conn, $empleos, $batch_size);
    insert_convocatorias($conn, $empleos, $batch_size);
    insert_dependencias($conn, $empleos, $batch_size);
    insert_municipios($conn, $empleos, $batch_size);
    insert_requisitos($conn, $empleos, $batch_size);
    insert_denominaciones($conn, $empleos, $batch_size);
    insert_funciones($conn, $empleos, $batch_size);
    insert_documentos($conn, $empleos, $batch_size);
    insert_vacantes($conn, $empleos, $batch_size);
    insert_empleos($conn, $rows, $batch_size);
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
    $cursor_key = 'pipeline_main';

    BatchScan::scan(
        $conn,
        $table_name,
        $query,
        $batch_size,
        $cursor_key,
        fn(array $rows) => process_batch($conn, $rows, $batch_size),
    );
}

