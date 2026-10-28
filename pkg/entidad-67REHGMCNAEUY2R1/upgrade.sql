CREATE OR REPLACE SEQUENCE entidad_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE entidad (
    id INT,
    code INT UNSIGNED,
    nit VARCHAR(100),
    nombre VARCHAR(1000),
    tipo_entidad JSON,
    tipo_entidad_id INT,

    PRIMARY KEY pk_entidad_id(id),
    UNIQUE KEY uk_entidad_code(code),
    FOREIGN KEY fk_entidad_tipo_entidad_id(tipo_entidad_id)
    REFERENCES tipo_entidad(id)
);
