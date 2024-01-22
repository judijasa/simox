DELIMITER //
CREATE OR REPLACE TRIGGER after_job_offer_snapshot_upsert_job_offer
AFTER INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
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
        denominacion        = NEW.denominacion,
        grado               = NEW.grado,
        codigo              = NEW.codigo,
        salario             = NEW.salario,
        vigencia_salarial   = NEW.vigencia_salarial,
        convocatoria        = NEW.convocatoria,
        entidad_codigo      = NEW.entidad_codigo,
        cierre              = NEW.cierre,
        vacantes            = NEW.vacantes,
        estudio             = NEW.estudio,
        dependencia         = NEW.dependencia,
        municipio           = NEW.municipio,
        max_snap_id         = NEW.id;
END; //
DELIMITER ;
