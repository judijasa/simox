CREATE OR REPLACE TABLE funcion (
    id INT AUTO_INCREMENT,
    code INT NOT NULL,
    descripcion TEXT,

    PRIMARY KEY pk_funcion_id(id),
    UNIQUE INDEX uk_funcion_code(code)
);

GRANT SELECT ON {{dbname}}.funcion TO 'public'@'{{servername}}';
