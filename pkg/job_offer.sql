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
    /* You can't make a foreign key from a col
    that's not unique in its parent tbl*/
    FOREIGN KEY job_offer_max_snap_id_fk(max_snap_id)
        REFERENCES job_offer_snapshot(id)
        ON UPDATE CASCADE ON DELETE CASCADE
);
/*
CHAR SET and COLLATE are now define at database level.

old version:
CREATE OR REPLACE TABLE myTable(
...
) DEFAULT CHARACTER SET utf8 COLLATE utf8_spanish_ci;
*/
