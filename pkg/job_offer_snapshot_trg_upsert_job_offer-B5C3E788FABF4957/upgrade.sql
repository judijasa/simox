DELIMITER //
CREATE OR REPLACE TRIGGER job_offer_snapshot_trg_upsert_job_offer
AFTER INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
    -- IF NEW.cierre IS NOT NULL OR NEW.cierre != '0000-00-00' THEN
    INSERT INTO job_offer (
        nivel_id,
        denominacion,
        grado,
        codigo,
        opec,
        salario,
        vigencia_salarial,
        convocatoria,
        entidad_codigo,
        cierre,
        vacantes,
        estudio,
        dependencia,
        municipio,
        max_snap_id
    ) VALUES (
        IF(
            NEW.nivel = 'NONE',
            NULL,
            (SELECT id FROM nivel WHERE nombre = NEW.nivel)
        ),
        null_from_default_str(NEW.denominacion),
        null_from_default_num(NEW.grado),
        null_from_default_str(NEW.codigo),
        NEW.opec,
        null_from_default_str(NEW.salario),
        null_from_default_year(NEW.vigencia_salarial),
        null_from_default_str(NEW.convocatoria),
        null_from_default_num(NEW.entidad_codigo),
        null_from_default_date(NEW.cierre),
        null_from_default_num(NEW.vacantes),
        null_from_default_str(NEW.estudio),
        null_from_default_str(NEW.dependencia),
        null_from_default_str(NEW.municipio),
        NEW.id
    ) ON DUPLICATE KEY
    UPDATE
        nivel_id = IF(
            NEW.nivel = 'NONE',
            NULL,
            (SELECT id FROM nivel WHERE nombre = NEW.nivel)
        ),
        denominacion        = null_from_default_str(NEW.denominacion),
        grado               = null_from_default_num(NEW.grado),
        codigo              = null_from_default_str(NEW.codigo),
        opec                = null_from_default_str(NEW.opec),
        salario             = null_from_default_str(NEW.salario),
        vigencia_salarial   = null_from_default_year(NEW.vigencia_salarial),
        convocatoria        = null_from_default_str(NEW.convocatoria),
        entidad_codigo      = null_from_default_num(NEW.entidad_codigo),
        cierre              = null_from_default_date(NEW.cierre),
        vacantes            = null_from_default_num(NEW.vacantes),
        estudio             = null_from_default_str(NEW.estudio),
        dependencia         = null_from_default_str(NEW.dependencia),
        municipio           = null_from_default_str(NEW.municipio),
        max_snap_id         = NEW.id;
        -- TODO: Condition UPDATE to at least one change
        -- END IF;
END; //
DELIMITER ;
