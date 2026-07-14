/*
To DROP a parent tbl, first you need to DROP its child tbls,
that is why we make sure job_offer is dropped before
replacing the vacante table.
*/
CREATE OR REPLACE TABLE vacante (
    id INT AUTO_INCREMENT,
    code INT NOT NULL, -- id at simo
    cantidad_ascensos SMALLINT UNSIGNED,
    municipio_code SMALLINT UNSIGNED,
    dependencia_code INT,
    fecha_generada TIMESTAMP,
    cantidad SMALLINT UNSIGNED,
    disponible SMALLINT UNSIGNED,
    cargos_vacantes JSON,
    ocupadas_pre_pensionados SMALLINT UNSIGNED,

    PRIMARY KEY(id),
    UNIQUE KEY uk_vacante_code(code),
    FOREIGN KEY fk_vacante_municipio_code(municipio_code)
        REFERENCES municipio(code),
    FOREIGN KEY fk_vacante_dependencia_code(dependencia_code)
        REFERENCES dependencia(code)
);

GRANT SELECT ON {{dbname}}.vacante TO 'public'@'{{servername}}';
