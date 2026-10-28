CREATE OR REPLACE SEQUENCE nivel_id_seq AS INT CACHE 50;

-- data taken from empleo.denominacion.nivel
CREATE OR REPLACE TABLE nivel (
    id INT,
    code SMALLINT UNSIGNED,
    nombre VARCHAR(100),

    PRIMARY KEY pk_nivel_id(id),
    UNIQUE KEY uk_nivel_code(code)
);
