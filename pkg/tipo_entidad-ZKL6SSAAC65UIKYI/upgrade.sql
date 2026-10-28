CREATE OR REPLACE SEQUENCE tipo_entidad_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE tipo_entidad (
    id INT,
    code TINYINT UNSIGNED,
    nombre VARCHAR(100),

    PRIMARY KEY pk_tipo_entidad_id(id),
    UNIQUE KEY uk_tipo_entidad_code(code)
);
