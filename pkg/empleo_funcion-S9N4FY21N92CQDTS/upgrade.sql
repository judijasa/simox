CREATE OR REPLACE TABLE empleo_funcion (
    empleo_id  INT NOT NULL,
    funcion_id INT NOT NULL,

    PRIMARY KEY (empleo_id, funcion_id),
    FOREIGN KEY fk_empleo_funcion_empleo_id(empleo_id)
        REFERENCES empleo(id),
    FOREIGN KEY fk_empleo_funcion_funcion_id(funcion_id)
        REFERENCES funcion(id)
);
