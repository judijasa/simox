CREATE OR REPLACE TABLE vacante (
    id INT AUTO_INCREMENT,
    code INT NOT NULL, -- id at simo
    cantidad_ascensos SMALLINT UNSIGNED,
    municipio_id SMALLINT UNSIGNED,
    dependencia_id INT,
    fecha_generada TIMESTAMP,
    cantidad SMALLINT UNSIGNED,
    disponible SMALLINT UNSIGNED,
    cargos_vacantes JSON,
    ocupadas_pre_pensionados SMALLINT UNSIGNED,

    PRIMARY KEY(id),
    UNIQUE KEY uk_vacante_code(code),
    FOREIGN KEY fk_vacante_municipio_id(municipio_id)
        REFERENCES municipio(id),
    FOREIGN KEY fk_vacante_dependencia_id(dependencia_id)
        REFERENCES dependencia(id)
);

GRANT SELECT ON {{dbname}}.vacante TO 'public'@'{{servername}}';
