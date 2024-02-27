-- To DROP a parent tbl, first you need to DROP its child tbls
DROP TABLE IF EXISTS estudio_especializado_variaciones;
CREATE OR REPLACE TABLE estudio_especializado (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(75) UNIQUE NOT NULL
);
