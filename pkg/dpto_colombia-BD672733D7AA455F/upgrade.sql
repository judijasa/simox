/* To DROP a parent tbl, first you need to DROP its child tbls */
DROP TABLE IF EXISTS job_offer;
CREATE OR REPLACE TABLE dpto_colombia (
    id TINYINT AUTO_INCREMENT,
    nombre VARCHAR(75) UNIQUE NOT NULL,

    PRIMARY KEY(id)
);

INSERT INTO dpto_colombia (
    nombre
) VALUES
    ('Amazonas'), ('Antioquia'),
    ('Arauca'), ('Archipiélago de San Andrés, Providencia y Santa Catalina'),
    ('Atlántico'), ('Bogotá D.C.'),
    ('Bolívar'), ('Boyacá'), ('Caldas'),
    ('Caquetá'), ('Casanare'),
    ('Cauca'), ('Cesar'),
    ('Chocó'), ('Córdoba'),
    ('Cundinamarca'), ('Guainía'),
    ('Guaviare'), ('Huila'),
    ('La Guajira'), ('Magdalena'),
    ('Meta'), ('Nariño'),
    ('Norte de Santander'), ('Putumayo'),
    ('Quindío'), ('Risaralda'),
    ('Santander'), ('Sucre'),
    ('Tolima'), ('Valle del Cauca'),
    ('Vaupés'), ('Vichada');

GRANT SELECT ON simo.dpto_colombia TO 'public'@'127.0.0.1';
