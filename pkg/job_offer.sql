CREATE OR REPLACE TABLE job_offer (
    id INT AUTO_INCREMENT,
    nivel_id SMALLINT,
    denominacion VARCHAR(250),
    grado TINYINT,
    codigo VARCHAR(10), -- check if this is unique
    opec INT UNIQUE NOT NULL,
    salario VARCHAR(50),
    vigencia_salarial YEAR,
    convocatoria VARCHAR(250),
    entidad_codigo SMALLINT,
    cierre DATE NOT NULL,
    vacantes SMALLINT,
    estudio TEXT,
    dependencia VARCHAR(5000),
    municipio VARCHAR(1000),
    /* municipio_id SMALLINT, -- TODO: after creating `municipio` norm tbl */
    departamento_id TINYINT,
    max_snap_id INT NOT NULL,
    keywords TEXT,

    PRIMARY KEY(id),
    UNIQUE INDEX(opec),
    INDEX idx_job_offer_cierre(cierre),
    INDEX idx_job_offer_departamento_id(departamento_id),
    FOREIGN KEY fk_job_offer_departamento_id(departamento_id)
        REFERENCES dpto_colombia(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_job_offer_nivel_id(nivel_id)
        REFERENCES nivel(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    /* You can't make a foreign key from a col
    that's not unique in its parent tbl*/
    FOREIGN KEY job_offer_max_snap_id_fk(max_snap_id)
        REFERENCES job_offer_snapshot(id)
        ON UPDATE CASCADE ON DELETE CASCADE
);

/*
old version:
CREATE OR REPLACE TABLE myTable(...
) DEFAULT CHARACTER SET utf8 COLLATE utf8_spanish_ci;

Note: CHAR SET and COLLATE now defined at database level.
*/

DELIMITER //
CREATE OR REPLACE FUNCTION norm_null_from_str(input_str VARCHAR(5000))
RETURNS VARCHAR(5000)
BEGIN
    DECLARE output_str VARCHAR(5000);

    IF input_str = 'NONE' THEN
        SET output_str = NULL;
    ELSE
        SET output_str = input_str;
    END IF;

    RETURN output_str;
END; //
DELIMITER ;

DELIMITER //
CREATE OR REPLACE FUNCTION norm_null_from_num(input_num INT)
RETURNS INT
BEGIN
    DECLARE output_num INT;

    IF input_num = -1 THEN
        SET output_num = NULL;
    ELSE
        SET output_num = input_num;
    END IF;

    RETURN output_num;
END; //
DELIMITER ;

DELIMITER //
CREATE OR REPLACE FUNCTION norm_null_from_date(input_date DATE)
RETURNS DATE
BEGIN
    DECLARE output_date DATE;

    IF input_date = '0000-00-00' THEN
        SET output_date = NULL;
    ELSE
        SET output_date = input_date;
    END IF;

    RETURN output_date;
END; //
DELIMITER ;

DELIMITER //
CREATE OR REPLACE FUNCTION norm_null_year(input_year YEAR)
RETURNS YEAR
BEGIN
    DECLARE output_year YEAR;

    IF input_year = 0000 THEN
        SET output_year = NULL;
    ELSE
        SET output_year = input_year;
    END IF;

    RETURN output_year;
END; //
DELIMITER ;
