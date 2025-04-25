DELIMITER //
CREATE OR REPLACE TRIGGER after_job_offer_snapshot_upsert_job_offer
AFTER INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
    IF NEW.cierre IS NOT NULL THEN
        INSERT INTO job_offer (
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
            departamento_id,
            max_snap_id
        ) VALUES (
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
            null_from_default_num(NEW.departamento_id),
            NEW.id
        ) ON DUPLICATE KEY
        UPDATE
            denominacion        = null_from_default_str(NEW.denominacion),
            grado               = null_from_default_num(NEW.grado),
            codigo              = null_from_default_str(NEW.codigo),
            salario             = null_from_default_str(NEW.salario),
            vigencia_salarial   = null_from_default_year(NEW.vigencia_salarial),
            convocatoria        = null_from_default_str(NEW.convocatoria),
            entidad_codigo      = null_from_default_num(NEW.entidad_codigo),
            cierre              = null_from_default_date(NEW.cierre),
            vacantes            = null_from_default_num(NEW.vacantes),
            estudio             = null_from_default_str(NEW.estudio),
            dependencia         = null_from_default_str(NEW.dependencia),
            municipio           = null_from_default_str(NEW.municipio),
            departamento_id     = null_from_default_num(NEW.departamento_id),
            max_snap_id         = NEW.id;
        END IF;
END; //
DELIMITER ;
