CREATE OR REPLACE TRIGGER after_job_offer_snapshot_insert_nivel
AFTER INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
    INSERT INTO nivel (
        nombre
    ) VALUES (
        NEW.nivel
    ) ON DUPLICATE KEY
    UPDATE
        id = id;
END;
