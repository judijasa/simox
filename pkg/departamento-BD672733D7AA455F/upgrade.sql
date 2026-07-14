/* To DROP a parent tbl, first you need to DROP its child tbls */
CREATE OR REPLACE TABLE departamento (
    id TINYINT AUTO_INCREMENT,
    nombre VARCHAR(100) UNIQUE NOT NULL,
    iso VARCHAR(5) UNIQUE NOT NULL,

    PRIMARY KEY pk_departamento_id(id),
    UNIQUE KEY uk_departamento(nombre)

);

INSERT INTO departamento (
    nombre,
    iso
) VALUES
    ('Amazonas', 'AMA'), ('Antioquia', 'ANT'),
    ('Arauca', 'ARA'), ('Archipiélago de San Andrés, Providencia y Santa Catalina', 'SAP'),
    ('Atlántico', 'ATL'), ('Bogotá D.C.', 'DC'),
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
    ('Vaupés', 'VAU'), ('Vichada', 'VID'),
    ('No_Aplica', 'N.A.');

GRANT SELECT ON {{dbname}}.departamento TO 'public'@'{{servername}}';
