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
            denominacion        = norm_null_str(NEW.denominacion),
            grado               = norm_null_num(NEW.grado),
            codigo              = norm_null_str(NEW.codigo),
            salario             = norm_null_str(NEW.salario),
            vigencia_salarial   = norm_null_year(NEW.vigencia_salarial),
            convocatoria        = norm_null_str(NEW.convocatoria),
            entidad_codigo      = norm_null_num(NEW.entidad_codigo),
            cierre              = norm_null_date(NEW.cierre),
            vacantes            = norm_null_num(NEW.vacantes),
            estudio             = norm_null_str(NEW.estudio),
            dependencia         = norm_null_str(NEW.dependencia),
            municipio           = norm_null_str(NEW.municipio),
            max_snap_id         = NEW.id;
        END IF;
END; //
DELIMITER ;
