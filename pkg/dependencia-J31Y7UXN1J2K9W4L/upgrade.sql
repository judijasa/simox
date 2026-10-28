CREATE OR REPLACE SEQUENCE dependencia_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE dependencia (
    id INT,
    code INT, -- simo id
    nombre VARCHAR(10000) UNIQUE NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_dependencia_code(code)
);
