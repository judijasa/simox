CREATE OR REPLACE TABLE empleo_requisito (
    empleo_id    INT NOT NULL,
    requisito_id INT NOT NULL,

    PRIMARY KEY (empleo_id, requisito_id),
    FOREIGN KEY fk_empleo_requisito_empleo_id(empleo_id)
        REFERENCES empleo(id),
    FOREIGN KEY fk_empleo_requisito_requisito_id(requisito_id)
        REFERENCES requisito(id)
);
