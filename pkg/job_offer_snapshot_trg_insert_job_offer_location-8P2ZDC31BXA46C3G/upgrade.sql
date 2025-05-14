/*
The motivation for this trigger is to avoid using SET or JSON
types job_offer because it is expensive to filter rows
that have a specific value in a SET or JSON type.
*/
DELIMITER //
CREATE OR REPLACE TRIGGER job_offer_snapshot_trg_insert_job_offer_location
BEFORE INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
    DECLARE str TEXT;
    DECLARE value TEXT;
    DECLARE comma_pos INT;

    SET str = NEW.departamento_ids;

    WHILE LENGTH(str) > 0 DO
        SET comma_pos = LOCATE(',', str);

        IF comma_pos > 0 THEN
            SET value = SUBSTRING(str, 1, comma_pos - 1);
            SET str = SUBSTRING(str, comma_pos + 1);
        ELSE
            SET value = str;
            SET str = '';
        END IF;

        INSERT INTO job_offer_location(opec, dpto_colombia_id)
        VALUES (NEW.opec, value)
        ON DUPLICATE KEY
        UPDATE
            opec = opec;
    END WHILE;
END; //
DELIMITER ;
