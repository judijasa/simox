CREATE OR REPLACE TABLE otras_habilidades_variaciones (
    id INT AUTO_INCREMENT,
    nombre VARCHAR(75) UNIQUE NOT NULL,
    otras_habilidades_id INT,

    PRIMARY KEY(id),
    CONSTRAINT otras_habilidades_otras_habilidades_id_fk
    FOREIGN KEY(otras_habilidades_id)
        REFERENCES otras_habilidades(id)
);

INSERT INTO otras_habilidades_variaciones (
    nombre
) VALUES
    ('Bachiller'), ('Terapias'), ('Justicia y Derecho'), ('Defensor de Familia');
