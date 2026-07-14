CREATE OR REPLACE TABLE documento (
    id INT AUTO_INCREMENT,
    codigo INT,  -- simo id
    ruta_archivo VARCHAR(100),
    content_type VARCHAR(100),
    version VARCHAR(100),
    stage_id VARCHAR(100),
    fecha VARCHAR(100), -- DATETIME
    documento_origen_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY pk_documento_id(id),
    UNIQUE INDEX uk_documento_codigo(codigo)
);

GRANT SELECT ON {{dbname}}.documento TO 'public'@'{{servername}}';
