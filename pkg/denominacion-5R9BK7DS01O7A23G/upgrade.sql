CREATE OR REPLACE TABLE denominacion (
    id TINYINT AUTO_INCREMENT,
    code TINYINT NOT NULL,  -- id at simo
    nivel TINYINT UNSIGNED,
    nombre VARCHAR(100) NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_denominacion_code (code)
);

GRANT SELECT ON {{dbname}}.denominacion TO 'public'@'{{servername}}';
