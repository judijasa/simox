CREATE OR REPLACE VIEW vw_empleo AS
SELECT
    e.id AS simox_id,
    e.created_date,
    e.opec AS opec,
    e.asignacion_salarial,
    e.codigo_empleo,
    e.sin_codigo,
    (
        SELECT
            json_object(
                'id', den.code,
                'nivel', den.nivel,
                'nombre', den.nombre
            )
        FROM denominacion den
        WHERE den.id = e.denominacion_id
    ) AS denominacion,
    e.descripcion,
    e.concurso_ascenso,
    e.condicion_discapacidad,
    e.grado_nivel,
    e.grado_denominacion,
    (
        SELECT
            json_object(
                'id', conv.code,
                'nombre', conv.nombre,
                'agno', conv.agno,
                'codigo', conv.codigo,
                'entidad', conv.entidad,
                'esTipoFase', conv.es_tipo_fase,
                'tipoProceso', conv.tipo_proceso,
                'noCobroNivel', conv.no_cobro_nivel,
                'noCobroOpec', conv.no_cobro_opec,
                'tipoProcSeleId', conv.tipo_proc_sele_id
            )
        FROM convocatoria conv
        WHERE conv.id = e.convocatoria_id
    ) AS convocatoria,
    (
        SELECT 
            json_arrayagg(
                json_object(
                    'id', fu.code,
                    'descripcion', fu.descripcion
                )
                ORDER BY fu.code
            )
        FROM funcion fu JOIN empleo_funcion e_f ON fu.id = e_f.funcion_id
        WHERE e_f.empleo_id = e.id
    ) AS funciones,
    (
        SELECT 
            json_arrayagg(
                json_object(
                    'id', req.code,
                    -- Avoid conflict within json_object using json_quote() on TEXT/VARCHAR types
                    -- that sometimes has inner quotes.
                    'estudio', json_quote(req.estudio),
                    'experiencia', json_quote(req.experiencia),
                    'otros', json_quote(req.otros),
                    'alternativas', req.alternativas,
                    'equivalencias', req.equivalencias
                )
                ORDER BY req.code
            )
        FROM requisito req JOIN empleo_requisito e_r ON req.id = e_r.requisito_id
        WHERE e_r.empleo_id = e.id
    ) AS requisitos,
    (
        SELECT 
            json_arrayagg(
                json_object(
                    'id', vac.code,
                    'cantidadAscensos', cantidad_ascensos,
                    'muncipio', municipio,
                    'dependencia', dependencia,
                    'fechaGenerada', fecha_generada,
                    'cantidad', cantidad,
                    'disponible', disponible,
                    'cargosVacantes', cargos_vacantes,
                    'ocupadasPrePensionados', ocupadas_pre_pensionados
                )
                ORDER BY vac.code
            )
        FROM vacante vac JOIN empleo_vacante e_v ON vac.id = e_v.vacante_id
        WHERE e_v.empleo_id = e.id
    ) AS vacantes,
    e.area,
    e.discapacidades,
    (
        SELECT
            JSON_OBJECT(
                'id', doc.code,
                'rutaArchivo', doc.ruta_archivo,
                'contentType', doc.content_type,
                'version', doc.version,
                'stageId', doc.stage_id,
                'fecha', doc.fecha,
                'documentoOrigenId', doc.documento_origen_id
            )
        FROM documento doc WHERE doc.id = e.documento_id
    ) AS documento,
    (
        SELECT
            JSON_OBJECT(
                'id', ent.code,
                'nit', ent.nit,
                'nombre', ent.nombre,
                'tipoEntidad', ent.tipo_entidad
            )
        FROM entidad ent WHERE ent.id = e.entidad_id
    ) AS entidad,
    e.identificador,
    e.vigencia_salarial,
    e.urbano,
    e.aeronautico,
    e.no_cobro_opec,
    e.estado_inscripcion,
    e.favorito,
    e.inscripcion_id,
    e.fecha_inscripcion,
    e.nivel_nombre,
    e.access
FROM empleo e;

GRANT SELECT ON {{dbname}}.vw_empleo TO 'public'@'{{servername}}';
