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
            max_snap_id
        ) VALUES (
            NEW.denominacion,
            NEW.grado,
            NEW.codigo,
            NEW.opec,
            NEW.salario,
            NEW.vigencia_salarial,
            NEW.convocatoria,
            NEW.entidad_codigo,
            NEW.cierre,
            NEW.vacantes,
            NEW.estudio,
            NEW.dependencia,
            NEW.municipio,
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
            max_snap_id         = NEW.id;
        END IF;
END; //
DELIMITER ;
