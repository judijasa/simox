DELIMITER //
CREATE OR REPLACE TRIGGER job_offer_snapshot_trg_upsert_job_offer
AFTER INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
    -- IF NEW.cierre IS NOT NULL OR NEW.cierre != '0000-00-00' THEN
    INSERT INTO job_offer (
        nivel_id
        , denominacion_id
        , grado
        , codigo
        , opec
        , salario
        , vigencia_salarial
        , convocatoria_id
        , entidad_codigo
        , cierre
        , max_snap_id
    ) VALUES (
        /*
            fill new tbls with
            BEFORE triggers to INSERT ON job_offer_snapshot
        */
        (
            SELECT n.id
            FROM nivel n
            WHERE n.grado = JSON_VALUE(NEW.empleo, '$.gradoNivel.grado')
        ) -- nivel_id
        , (
            SELECT d.id
            FROM denominacion d
            WHERE d.simo_id = JSON_VALUE(NEW.empleo, '$.denominacion.id')
        ) -- denominacion_id
        , JSON_VALUE(NEW.empleo, '$.gradoDenominacion.grado') -- grado
        , JSON_VALUE(NEW.empleo, '$.codigoEmpleo') -- codigo
        , JSON_VALUE(NEW.empleo, '$.id') -- opec
        , JSON_VALUE(NEW.empleo, '$.asignacionSalarial')
        , JSON_VALUE(NEW.empleo, '$.vigenciaSalarial')
        , (
            SELECT c.id
            FROM convocatoria c
            WHERE c.simo_id = JSON_VALUE(NEW.empleo, '$.convocatoria.id')
        ) -- convocatoria_id
        , JSON_VALUE(NEW.empleo, '$.identificador') -- entidad codigo
        , JSON_VALUE(NEW.empleo, '$.fechaInscripcion') -- cierre
        , NEW.id -- max_snapshot_id
    ) ON DUPLICATE KEY
    UPDATE
        nivel_id = IF(
            nivel_id != (
                SELECT n.id
                FROM nivel n
                WHERE n.grado = JSON_VALUE(NEW.empleo, '$.gradoNivel.grado')
            ), (
                SELECT n.id
                FROM nivel n
                WHERE n.grado = JSON_VALUE(NEW.empleo, '$.gradoNivel.grado')
            ), nivel_id
        ), denominacion_id = IF(
            denominacion_id != (
                SELECT d.id
                FROM denominacion d
                WHERE d.simo_id = JSON_VALUE(NEW.empleo, '$.denominacion.id')
            ), (
                SELECT d.id
                FROM denominacion d
                WHERE d.simo_id = JSON_VALUE(NEW.empleo, '$.denominacion.id')
            ), denominacion_id
        ), grado = IF(
            grado != JSON_VALUE(NEW.empleo, '$.gradoDenominacion.grado'),
            JSON_VALUE(NEW.empleo, '$.gradoDenominacion.grado'),
            grado
        ), codigo = IF(
            codigo != JSON_VALUE(NEW.empleo, '$.codigoEmpleo'),
            JSON_VALUE(NEW.empleo, '$.codigoEmpleo'),
            codigo
        ), opec = IF(
            opec != JSON_VALUE(NEW.empleo, '$.id'),
            JSON_VALUE(NEW.empleo, '$.id'),
            opec
        ), salario = IF(
            salario != JSON_VALUE(NEW.empleo, '$.asignacionSalarial'),
            JSON_VALUE(NEW.empleo, '$.asignacionSalarial'),
            salario
        ), vigencia_salarial = JSON_VALUE(NEW.empleo, '$.vigenciaSalarial')
        , convocatoria_id = IF(
            convocatoria_id != (
                SELECT c.id
                FROM convocatoria c
                WHERE c.simo_id = JSON_VALUE(NEW.empleo, '$.convocatoria.id')
            ), (
                SELECT c.id
                FROM convocatoria c
                WHERE c.simo_id = JSON_VALUE(NEW.empleo, '$.convocatoria.id')
            ), convocatoria_id
        ), entidad_codigo = IF(
            entidad_codigo != JSON_VALUE(NEW.empleo, '$.identificador'),
            JSON_VALUE(NEW.empleo, '$.identificador'),
            entidad_codigo
        ), cierre = IF(
            cierre != JSON_VALUE(NEW.empleo, '$.fechaInscripcion'),
            JSON_VALUE(NEW.empleo, '$.fechaInscripcion'),
            cierre
        ), max_snap_id = NEW.id;
END; //
DELIMITER ;
