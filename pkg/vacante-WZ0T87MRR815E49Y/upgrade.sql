CREATE OR REPLACE SEQUENCE vacante_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE vacante (
    id INT,
    code INT NOT NULL, -- id at simo
    cantidad_ascensos SMALLINT UNSIGNED,
    municipio JSON, -- to avoid subqueries of 3rd order in empleo view
    municipio_id INT, -- to search by municipio efficiently
    dependencia JSON, -- to avoid subqueries of 3rd order in empleo view
    dependencia_id INT, -- to search by dependencia efficiently
    fecha_generada DATE,
    fecha_generada_src VARCHAR(64),
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
