/* To DROP a parent tbl, first you need to DROP its child tbls */
DROP TABLE IF EXISTS job_offer;
CREATE OR REPLACE TABLE job_offer_snapshot (
    id INT AUTO_INCREMENT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pagina SMALLINT NOT NULL DEFAULT -1,
    nivel VARCHAR(100) NOT NULL DEFAULT 'NONE',
    denominacion VARCHAR(250) NOT NULL DEFAULT 'NONE',
    grado TINYINT NOT NULL DEFAULT -1,
    codigo VARCHAR(10) NOT NULL DEFAULT 'NONE',
    opec INT NOT NULL DEFAULT -1,
    salario VARCHAR(50) NOT NULL DEFAULT 'NONE',
    vigencia_salarial YEAR NOT NULL DEFAULT 0000,
    convocatoria VARCHAR(250) NOT NULL DEFAULT 'NONE',
    entidad_codigo SMALLINT NOT NULL DEFAULT -1,
    cierre DATE NOT NULL DEFAULT '0000-00-00',
    vacantes SMALLINT NOT NULL DEFAULT -1,
    estudio TEXT NOT NULL DEFAULT 'NONE',
    experiencia TEXT NOT NULL DEFAULT 'NONE',
    dependencia VARCHAR(10000) NOT NULL DEFAULT 'NONE',
    municipio VARCHAR(10000) NOT NULL DEFAULT 'NONE', /* e.g. `Cali`, `Buga, Cartago`, `Leticia`*/
    otros TEXT NOT NULL DEFAULT 'NONE',

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
