CREATE OR REPLACE SEQUENCE convocatoria_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE convocatoria (
    id INT,
    code INT,  -- id by simo
    nombre VARCHAR(512),
    agno YEAR,
    codigo VARCHAR(255),
    entidad JSON,
    entidad_id INT,
    es_tipo_fase BOOL,
    tipo_proceso VARCHAR(100),
    no_cobro_nivel BOOL,
    no_cobro_opec BOOL,
    convocatoria_padre_id INT,
    publicar_opec BOOL,
    opec_historico BOOL,
    tipo_proc_sele_id TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY pk_convocatoria_id(id),
    UNIQUE KEY uk_convocatoria_code(code),
    INDEX idx_job_offer_agno(agno),
    FOREIGN KEY fk_convocatoria_entidad_id(entidad_id)
    REFERENCES entidad(id)
);
