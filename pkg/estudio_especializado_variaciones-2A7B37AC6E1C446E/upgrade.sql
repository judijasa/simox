CREATE OR REPLACE TABLE estudio_especializado_variaciones (
    id INT AUTO_INCREMENT,
    nombre VARCHAR(75) UNIQUE NOT NULL,
    estudio_especializado_id INT,

    PRIMARY KEY(id),
    CONSTRAINT estudio_especializado_variaciones_estudio_especializado_id_fk
    FOREIGN KEY(estudio_especializado_id)
        REFERENCES estudio_especializado(id)
);

INSERT INTO estudio_especializado_variaciones (
    nombre
) VALUES
    ('Contaduría Pública'), ('Contaduría Internacional'), ('Relaciones Internacionales'),
    ('Derecho Laboral'), ('Derecho Laboral y Seguridad Social'), ('Derecho del Trabajo'),
    ('Derecho del Trabajo y Seguridad Social'), ('Derecho Procesal'), ('Derechos Humanos'),
    ('Derecho Constitucional'), ('Derecho Administrativo'), ('Derecho Civil'),
    ('Derecho de Familia'), ('Jurisprudencia'), ('Comercio Internacional'),
    ('Administración Pública'), ('Administración de Empresas'), ('Administración de Empresas o Pública'),
    ('Administración Financiera'), ('Financiera'), ('Finanzas'),
    ('Administración Ambiental'), ('Administración Ambiental y de los Recursos Naturales'), ('Psicología Familiar');
