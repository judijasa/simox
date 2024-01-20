/* To DROP a parent tbl, first you need to DROP its child tbls */
DROP TABLE IF EXISTS job_offer;
CREATE OR REPLACE TABLE job_offer_snapshot (
    id INT AUTO_INCREMENT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pagina SMALLINT,
    nivel VARCHAR(100),
    denominacion VARCHAR(250),
    grado TINYINT,
    codigo VARCHAR(10),
    opec INT, -- old version: VARCHAR(50),
    salario VARCHAR(50),
    vigencia_salarial YEAR,
    convocatoria VARCHAR(250),
    entidad_codigo SMALLINT,
    cierre DATE,
    vacantes SMALLINT,
    estudio TEXT,
    experiencia TEXT,
    dependencia VARCHAR(5000),
    municipio VARCHAR(1000),
    otros TEXT,

    PRIMARY KEY(id),
    UNIQUE KEY unique_job_offer_snapshot (
        nivel,
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
        experiencia,
        dependencia,
        municipio,
        otros
    )
);
