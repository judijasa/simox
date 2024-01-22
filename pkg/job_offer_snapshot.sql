/* To DROP a parent tbl, first you need to DROP its child tbls */
DROP TABLE IF EXISTS job_offer;
CREATE OR REPLACE TABLE job_offer_snapshot (
    id INT AUTO_INCREMENT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pagina SMALLINT DEFAULT -1,
    nivel VARCHAR(100) DEFAULT 'NONE',
    denominacion VARCHAR(250) DEFAULT 'NONE',
    grado TINYINT DEFAULT -1,
    codigo VARCHAR(10) DEFAULT 'NONE',
    opec INT DEFAULT -1,
    salario VARCHAR(50) DEFAULT 'NONE',
    vigencia_salarial YEAR DEFAULT 0000,
    convocatoria VARCHAR(250) DEFAULT 'NONE',
    entidad_codigo SMALLINT DEFAULT -1,
    cierre DATE DEFAULT '0000-00-00',
    vacantes SMALLINT DEFAULT -1,
    estudio TEXT DEFAULT 'NONE',
    experiencia TEXT DEFAULT 'NONE',
    dependencia VARCHAR(5000) DEFAULT 'NONE',
    municipio VARCHAR(1000) DEFAULT 'NONE',
    otros TEXT DEFAULT 'NONE',

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
/*  The DEFAULT values create a pseudo NULL, necessary to prevent
    duplicates of duplicated rows due to NULL values in
    the unique key. */
