CREATE OR REPLACE TABLE convocatoria (
    id INT AUTO_INCREMENT,
    code INT,  -- id by simo
    nombre VARCHAR(250),
    agno YEAR,
    codigo VARCHAR(10),
    entidad JSON,
    es_tipo_fase BOOL,
    tipo_proceso VARCHAR(100),
    no_cobro_nivel BOOL,
    no_cobro_opec BOOL,
    tipo_proc_sele_id TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY(id),
    UNIQUE INDEX idx_convocatoria_code(code),
    INDEX idx_job_offer_agno(agno)
);

GRANT SELECT ON {{dbname}}.convocatoria TO 'public'@'{{servername}}';
