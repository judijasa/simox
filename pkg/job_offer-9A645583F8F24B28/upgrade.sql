CREATE OR REPLACE TABLE job_offer (
    id INT AUTO_INCREMENT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    nivel_id SMALLINT UNSIGNED,
    denominacion_id INT,
    grado TINYINT,
    codigo VARCHAR(10), -- check if this is unique
    opec INT NOT NULL,
    salario VARCHAR(50),
    vigencia_salarial YEAR,
    convocatoria_id INT,
    entidad_codigo SMALLINT,
    cierre DATE NOT NULL,
    vacante_id INT,
    requisito_id INT,
    -- estudio TEXT,
    /* Using `municipio_id SMALLINT`, after creating `municipio` norm tbl
    not possible because municipio can be more than one e.g. `Cali, Buga`*/
    max_snap_id INT NOT NULL,
    keywords TEXT,

    PRIMARY KEY(id),
    UNIQUE INDEX(opec),
    INDEX idx_job_offer_created_at(created_at),
    INDEX idx_job_offer_cierre(cierre),
    /* You can't make a foreign key from a col
    that's not unique in its parent tbl*/
    FOREIGN KEY fk_job_offer_nivel_id(nivel_id)
        REFERENCES nivel(id),
        -- ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_job_offer_denominacion_id(denominacion_id)
        REFERENCES denominacion(id),
        -- ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_job_offer_requisito_id(requisito_id)
        REFERENCES requisito(id),
        -- ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_job_offer_vacante_id(vacante_id)
        REFERENCES vacante(id),
        -- ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_job_offer_max_snap_id(max_snap_id)
        REFERENCES job_offer_snapshot(id)
        -- ON UPDATE CASCADE ON DELETE CASCADE
);

/*
old version:
CREATE OR REPLACE TABLE myTable(...
) DEFAULT CHARACTER SET utf8 COLLATE utf8_spanish_ci;

Note: CHAR SET and COLLATE now defined at database level.
*/

GRANT SELECT ON {{dbname}}.job_offer TO 'public'@'{{servername}}';

DELIMITER //
CREATE OR REPLACE FUNCTION null_from_default_str(input_str TEXT)
RETURNS TEXT
BEGIN
    DECLARE output_str TEXT;

    IF input_str = 'NONE' THEN
        SET output_str = NULL;
    ELSE
        SET output_str = input_str;
    END IF;

    RETURN output_str;
END; //
DELIMITER ;

DELIMITER //
CREATE OR REPLACE FUNCTION null_from_default_num(input_num INT)
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
CREATE OR REPLACE FUNCTION null_from_default_date(input_date DATE)
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
CREATE OR REPLACE FUNCTION null_from_default_year(input_year YEAR)
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
