CREATE OR REPLACE SEQUENCE funcion_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE funcion (
    id INT,
    code INT NOT NULL,
    descripcion TEXT,

    PRIMARY KEY pk_funcion_id(id),
    UNIQUE INDEX uk_funcion_code(code)
);
