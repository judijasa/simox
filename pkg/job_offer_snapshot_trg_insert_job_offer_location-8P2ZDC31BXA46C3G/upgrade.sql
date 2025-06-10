/*
The motivation for this trigger is to avoid using SET or JSON
types job_offer because it is expensive to filter rows
that have a specific value in a SET or JSON type.
*/
DELIMITER //

CREATE PROCEDURE insert_selected_fields(IN json_array JSON, IN opec_val INT)
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE n INT;
    DECLARE elem JSON;
    -- DECLARE name_val VARCHAR(255);
    DECLARE code_val INT;
    DECLARE cantidad_ascensos_val SMALLINT UNSIGNED;
    DECLARE location_code_val INT;
    DECLARE dependencia_code_val SMALLINT UNSIGNED;
    DECLARE fecha_generada_val DATE;
    DECLARE cantidad_val SMALLINT UNSIGNED;
    DECLARE disponible_val SMALLINT UNSIGNED;
    DECLARE cargos_vacantes_val JSON;
    DECLARE ocupadas_pre_pensionados_val SMALLINT UNSIGNED;

    SET n = JSON_LENGTH(json_array);

    WHILE i < n DO
        SET elem = JSON_EXTRACT(json_array, CONCAT('$[', i, ']'));

        -- SET name_val = JSON_UNQUOTE(JSON_EXTRACT(elem, '$.id'));
        SET code_val = JSON_VALUE(elem, '$.id');
        SET cantidad_ascensos_val = JSON_VALUE(elem, '$.municipio.id');
        SET location_code_val = JSON_VALUE(elem, '$.municipio.id');
        SET dependencia_code_val = JSON_VALUE(elem, '$.dependencia.id');
        SET fecha_generada_val = JSON_VALUE(elem, '$.fechaGenerada');
        SET cantidad_val = JSON_VALUE(elem, '$.cantidad');
        SET disponible_val = JSON_VALUE(elem, '$.disponible');
        SET cargos_vacantes_val = JSON_VALUE(elem, '$.cargosVacantes');
        SET ocupadas_pre_pensionados_val = JSON_VALUE(elem, '$.ocupadasPrePensionados');

        INSERT INTO vacante(
            code
            , opec
            , cantidad_ascensos
            , location_id
            , dependencia_id
            , fecha_generada
            , cantidad
            , disponible
            , cargos_vacantes
            , ocupadas_pre_pensionados
        ) VALUES (
            code_val
            , opec_val
            , cantidad_ascensos_val
            , (
                SELECT id
                FROM location
                WHERE code = location_code_val LIMIT 1
            ), (
                SELECT id
                FROM dependencia
                WHERE code = dependencia_code_val LIMIT 1
            ), fecha_generada_val
            , cantidad_val
            , disponible_val
            , cargos_vacantes_val
            , ocupadas_pre_pensionados_val
        ) ON DUPLICATE KEY UPDATE id = id;

        INSERT INTO location(code, municipio, departamento_id)
        VALUES (
            code_val,
            municipio_val,
            (
                SELECT id
                FROM departamento
                WHERE nombre = departamento_nombre_val
            )
        ) ON DUPLICATE KEY UPDATE id = id;

        SET i = i + 1;
    END WHILE;
END;
//

DELIMITER ;

DELIMITER //

CREATE TRIGGER trg_after_insert_json_source
AFTER INSERT ON job_offer_snapshot
FOR EACH ROW
BEGIN
    CALL insert_json_array_elements(JSON_VALUE(NEW.empleo, '$.vacantes'), NEW.id);
END;
//

DELIMITER ;
