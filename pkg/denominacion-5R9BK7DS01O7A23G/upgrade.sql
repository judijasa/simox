CREATE OR REPLACE TABLE denominacion (
    id INT AUTO_INCREMENT,
    code INT NOT NULL,  -- id at simo
    nivel JSON,
    nivel_id SMALLINT UNSIGNED,
    nombre VARCHAR(100) NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_denominacion_code(code),
    FOREIGN KEY fk_denominacion_nivel_id(nivel_id)
    REFERENCES nivel(id)
);

GRANT SELECT ON {{dbname}}.denominacion TO 'public'@'{{servername}}';
