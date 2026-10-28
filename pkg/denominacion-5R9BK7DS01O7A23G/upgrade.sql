CREATE OR REPLACE SEQUENCE denominacion_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE denominacion (
    id INT,
    code INT NOT NULL,  -- id at simo
    nivel JSON,
    nivel_id INT,
    nombre VARCHAR(255) NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_denominacion_code(code),
    FOREIGN KEY fk_denominacion_nivel_id(nivel_id)
    REFERENCES nivel(id)
);
