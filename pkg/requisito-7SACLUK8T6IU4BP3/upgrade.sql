CREATE OR REPLACE SEQUENCE requisito_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE requisito (
    id INT,
    code INT,
    estudio TEXT,
    experiencia TEXT,
    otros TEXT,
    alternativas JSON,
    equivalencias JSON,

    PRIMARY KEY pk_requisito_id(id),
    UNIQUE INDEX uk_requisito_code(code)
);
