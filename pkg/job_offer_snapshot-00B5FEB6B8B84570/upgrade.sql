/* To DROP a parent tbl, first you need to DROP its child tbls */
CREATE OR REPLACE TABLE job_offer_snapshot (
    id INT AUTO_INCREMENT,
    opec INT NOT NULL,
    empleo JSON,
    estado_inscripcion VARCHAR(100),
    favorito BOOLEAN,
    fecha_inscripcion TIMESTAMP,
    nivel_nombre VARCHAR(100),
    acceso JSON,

    PRIMARY KEY(id),
    UNIQUE KEY uk_job_offer_snapshot (
        empleo,
        fecha_inscripcion,
        acceso
    )
);
/*
    Note:
    We use pseudo-NULL values as default values
    because unique keys are unable to prevent
    duplicates in the presence of NULL values.

*/
