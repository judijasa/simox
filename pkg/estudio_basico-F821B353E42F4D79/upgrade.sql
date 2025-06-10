-- To DROP a parent tbl, first you need to DROP its child tbls
-- estudio_basico = Nucleo Basico de Estudio
CREATE OR REPLACE TABLE estudio_basico (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(75) UNIQUE NOT NULL
);
