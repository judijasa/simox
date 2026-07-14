CREATE OR REPLACE TABLE denominacion (
    id INT AUTO_INCREMENT,
    code INT NOT NULL,  -- id at simo
    nivel_code TINYINT,
    nombre VARCHAR(100) NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_denominacion_code (code),
    FOREIGN KEY fk_denominacion_nivel_code(nivel_code)
        REFERENCES nivel(code)
);

GRANT SELECT ON {{dbname}}.denominacion TO 'public'@'{{servername}}';
