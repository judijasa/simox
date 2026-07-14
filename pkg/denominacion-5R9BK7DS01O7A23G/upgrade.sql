CREATE OR REPLACE TABLE denominacion (
    id INT AUTO_INCREMENT,
    code INT NOT NULL,  -- id at simo
    nivel VARCHAR(100),
    nombre VARCHAR(100) NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_denominacion_code (code)
);

GRANT SELECT ON {{dbname}}.denominacion TO 'public'@'{{servername}}';
