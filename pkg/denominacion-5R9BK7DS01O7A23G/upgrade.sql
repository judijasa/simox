CREATE OR REPLACE TABLE denominacion (
    id TINYINT AUTO_INCREMENT,
    code TINYINT NOT NULL,  -- id at simo
    nivel_id TINYINT,
    nombre VARCHAR(100) NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_denominacion_code (code),
    FOREIGN KEY fk_denominacion_nivel_id(nivel_id)
        REFERENCES nivel(id)
);

GRANT SELECT ON {{dbname}}.denominacion TO 'public'@'{{servername}}';
