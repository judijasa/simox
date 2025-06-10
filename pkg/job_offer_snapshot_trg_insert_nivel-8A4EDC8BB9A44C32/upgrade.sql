DELIMITER //
CREATE OR REPLACE TRIGGER job_offer_snapshot_trg_insert_nivel
BEFORE INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
    IF NEW.empleo != NULL THEN
        INSERT INTO nivel(simo_id, nombre)
        VALUES (
            JSON_VALUE(NEW.empleo, '$.gradoNivel.grado'),
            JSON_VALUE(NEW.empleo, '$.gradoNivel.nivelNombre')
        )
        ON DUPLICATE KEY
        UPDATE
            id = id;
    END IF;
END; //
DELIMITER ;
