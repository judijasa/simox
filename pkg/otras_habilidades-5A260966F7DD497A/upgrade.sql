-- To DROP a parent tbl, first you need to DROP its child tbls
DROP TABLE IF EXISTS otras_habilidades_variaciones;
CREATE OR REPLACE TABLE otras_habilidades (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(75) UNIQUE NOT NULL
);
