CREATE OR REPLACE TABLE requisito (
    id INT AUTO_INCREMENT,
    code INT,
    estudio TEXT,
    experiencia TEXT,
    otros TEXT,
    alternativas JSON,
    equivalencias JSON,

    PRIMARY KEY pk_requisito_id(id),
    UNIQUE INDEX uk_requisito_code(code)
);

GRANT SELECT ON {{dbname}}.requisito TO 'public'@'{{servername}}';
