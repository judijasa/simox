CREATE OR REPLACE TABLE tipo_entidad (
    id TINYINT UNSIGNED AUTO_INCREMENT,
    code TINYINT UNSIGNED,
    nombre VARCHAR(100),

    PRIMARY KEY pk_tipo_entidad_id(id),
    UNIQUE KEY uk_tipo_entidad_code(code)
);

GRANT SELECT ON {{dbname}}.nivel TO 'public'@'{{servername}}';

