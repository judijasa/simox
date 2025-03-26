/* To DROP a parent tbl, first you need to DROP its child tbls */
DROP TABLE IF EXISTS job_offer;
CREATE OR REPLACE TABLE dpto_colombia (
    id TINYINT AUTO_INCREMENT,
    nombre VARCHAR(75) UNIQUE NOT NULL,
    iso VARCHAR(5) UNIQUE NOT NULL,

    PRIMARY KEY(id)
);

INSERT INTO dpto_colombia (
    nombre,
    iso
) VALUES
    ('Amazonas', 'AMA'), ('Antioquia', 'ANT'),
    ('Arauca', 'ARA'), ('Archipiélago de San Andrés, Providencia y Santa Catalina', 'SAP'),
    ('Atlántico', 'ATL'), ('Bogotá', 'DC'),
    ('Bolívar', 'BOL'), ('Boyacá', 'BOY'), ('Caldas', 'CAL'),
    ('Caquetá', 'CAQ'), ('Casanare', 'CAS'),
    ('Cauca', 'CAU'), ('Cesar', 'CES'),
    ('Chocó', 'CHO'), ('Córdoba', 'COR'),
    ('Cundinamarca', 'CUN'), ('Guainía', 'GUA'),
    ('Guaviare', 'GUV'), ('Huila', 'HUI'),
    ('La Guajira', 'LAG'), ('Magdalena', 'MAG'),
    ('Meta', 'MET'), ('Nariño', 'NAR'),
    ('Norte de Santander', 'NSA'), ('Putumayo', 'PUT'),
    ('Quindío', 'QUI'), ('Risaralda', 'RIS'),
    ('Santander', 'SAN'), ('Sucre', 'SUC'),
    ('Tolima', 'TOL'), ('Valle del Cauca', 'VAC'),
    ('Vaupés', 'VAU'), ('Vichada', 'VID');

GRANT SELECT ON {{dbname}}.dpto_colombia TO 'public'@'{{servername}}';
