CREATE OR REPLACE TABLE municipio (
    id SMALLINT UNSIGNED AUTO_INCREMENT,
    code SMALLINT UNSIGNED NOT NULL, -- id at simo
    nombre VARCHAR(100) NOT NULL,
    departamento VARCHAR(100),
    departamento_id TINYINT,

    PRIMARY KEY pk_municipio_id(id),
    UNIQUE KEY uk_municipio_code(code),
    FOREIGN KEY fk_municipio_departamento_id(departamento_id)
        REFERENCES departamento(id)
);

-- INSERT INTO municipio(id, code, nombre, departamento, departamento_id) VALUES (0, 0, 'No_Aplica', 'No_aplica', 34);
