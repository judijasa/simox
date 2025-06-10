CREATE OR REPLACE TABLE location (
    id SMALLINT UNSIGNED AUTO_INCREMENT,
    code SMALLINT UNSIGNED NOT NULL, -- id at simo
    municipio VARCHAR(75) NOT NULL,
    departamento_id TINYINT,

    PRIMARY KEY (id),
    UNIQUE KEY (code)
);

INSERT INTO location(id, code, municipio, departamento_id) VALUES (0, 0, 'No_Aplica', 34);
