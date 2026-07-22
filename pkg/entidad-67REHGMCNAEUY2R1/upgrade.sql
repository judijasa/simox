CREATE OR REPLACE TABLE entidad (
    id SMALLINT UNSIGNED AUTO_INCREMENT,
    code SMALLINT UNSIGNED,
    nit VARCHAR(100),
    nombre VARCHAR(100),
    tipo_entidad JSON,
    tipo_entidad_id TINYINT UNSIGNED,

    PRIMARY KEY pk_entidad_id(id),
    UNIQUE KEY uk_entidad_code(code),
    FOREIGN KEY fk_entidad_tipo_entidad_id(tipo_entidad_id)
    REFERENCES tipo_entidad(id)
);

GRANT SELECT ON {{dbname}}.entidad TO 'public'@'{{servername}}';

