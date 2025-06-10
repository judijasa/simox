/*
To DROP a parent tbl, first you need to DROP its child tbls,
that is why we make sure job_offer is dropped before
replacing the vacante table.
*/
CREATE OR REPLACE TABLE vacante (
    id INT AUTO_INCREMENT,
    code INT NOT NULL, -- id at simo
    opec INT NOT NULL,
    cantidad_ascensos SMALLINT UNSIGNED,
    location_id SMALLINT UNSIGNED,
    dependencia_id INT,
    fecha_generada TIMESTAMP,
    cantidad SMALLINT UNSIGNED,
    disponible SMALLINT UNSIGNED,
    cargos_vacantes JSON,
    ocupadas_pre_pensionados SMALLINT UNSIGNED,

    PRIMARY KEY(id),
    UNIQUE KEY uk_vacante_code(code),
    INDEX idx_vacante_opec(opec),
    FOREIGN KEY fk_vacante_location_id(location_id)
        REFERENCES location(id),
    FOREIGN KEY fk_vacante_dependencia_id(dependencia_id)
        REFERENCES dependencia(id)
);

GRANT SELECT ON {{dbname}}.vacante TO 'public'@'{{servername}}';
