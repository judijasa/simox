CREATE OR REPLACE SEQUENCE departamento_id_seq AS INT CACHE 50;

/* To DROP a parent tbl, first you need to DROP its child tbls */
CREATE OR REPLACE TABLE departamento (
    id INT,
    nombre VARCHAR(100) UNIQUE NOT NULL,
    iso VARCHAR(5) UNIQUE NOT NULL,

    PRIMARY KEY pk_departamento_id(id),
    UNIQUE KEY uk_departamento(nombre)

);

INSERT INTO departamento (
    id,
    nombre,
    iso
) VALUES
    (NEXT VALUE FOR departamento_id_seq, 'Amazonas', 'AMA'),
    (NEXT VALUE FOR departamento_id_seq, 'Antioquia', 'ANT'),
    (NEXT VALUE FOR departamento_id_seq, 'Arauca', 'ARA'),
    (NEXT VALUE FOR departamento_id_seq, 'Archipiélago de San Andrés, Providencia y Santa Catalina', 'SAP'),
    (NEXT VALUE FOR departamento_id_seq, 'Atlántico', 'ATL'),
    (NEXT VALUE FOR departamento_id_seq, 'Bogotá D.C.', 'DC'),
    (NEXT VALUE FOR departamento_id_seq, 'Bolívar', 'BOL'),
    (NEXT VALUE FOR departamento_id_seq, 'Boyacá', 'BOY'),
    (NEXT VALUE FOR departamento_id_seq, 'Caldas', 'CAL'),
    (NEXT VALUE FOR departamento_id_seq, 'Caquetá', 'CAQ'),
    (NEXT VALUE FOR departamento_id_seq, 'Casanare', 'CAS'),
    (NEXT VALUE FOR departamento_id_seq, 'Cauca', 'CAU'),
    (NEXT VALUE FOR departamento_id_seq, 'Cesar', 'CES'),
    (NEXT VALUE FOR departamento_id_seq, 'Chocó', 'CHO'),
    (NEXT VALUE FOR departamento_id_seq, 'Córdoba', 'COR'),
    (NEXT VALUE FOR departamento_id_seq, 'Cundinamarca', 'CUN'),
    (NEXT VALUE FOR departamento_id_seq, 'Guainía', 'GUA'),
    (NEXT VALUE FOR departamento_id_seq, 'Guaviare', 'GUV'),
    (NEXT VALUE FOR departamento_id_seq, 'Huila', 'HUI'),
    (NEXT VALUE FOR departamento_id_seq, 'La Guajira', 'LAG'),
    (NEXT VALUE FOR departamento_id_seq, 'Magdalena', 'MAG'),
    (NEXT VALUE FOR departamento_id_seq, 'Meta', 'MET'),
    (NEXT VALUE FOR departamento_id_seq, 'Nariño', 'NAR'),
    (NEXT VALUE FOR departamento_id_seq, 'Norte de Santander', 'NSA'),
    (NEXT VALUE FOR departamento_id_seq, 'Putumayo', 'PUT'),
    (NEXT VALUE FOR departamento_id_seq, 'Quindío', 'QUI'),
    (NEXT VALUE FOR departamento_id_seq, 'Risaralda', 'RIS'),
    (NEXT VALUE FOR departamento_id_seq, 'Santander', 'SAN'),
    (NEXT VALUE FOR departamento_id_seq, 'Sucre', 'SUC'),
    (NEXT VALUE FOR departamento_id_seq, 'Tolima', 'TOL'),
    (NEXT VALUE FOR departamento_id_seq, 'Valle del Cauca', 'VAC'),
    (NEXT VALUE FOR departamento_id_seq, 'Vaupés', 'VAU'),
    (NEXT VALUE FOR departamento_id_seq, 'Vichada', 'VID'),
    (NEXT VALUE FOR departamento_id_seq, 'No_Aplica', 'N.A.');
