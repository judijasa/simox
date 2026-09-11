CREATE OR REPLACE TABLE empleo_vacante (
    empleo_id  INT NOT NULL,
    vacante_id INT NOT NULL,

    PRIMARY KEY (empleo_id, vacante_id),
    FOREIGN KEY fk_empleo_vacante_empleo_id(empleo_id)
        REFERENCES empleo(id),
    FOREIGN KEY fk_empleo_vacante_vacante_id(vacante_id)
        REFERENCES vacante(id)
);
