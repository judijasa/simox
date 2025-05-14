DELIMITER //
CREATE OR REPLACE TRIGGER job_offer_snapshot_trg_insert_nivel
BEFORE INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
    IF NEW.nivel != 'NONE' THEN
        INSERT INTO nivel(nombre)
        VALUES (NEW.nivel)
        ON DUPLICATE KEY
        UPDATE
            id = id;
    END IF;
END; //
DELIMITER ;
