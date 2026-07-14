-- data taken from empleo.denominacion.nivel
CREATE OR REPLACE TABLE nivel (
    id SMALLINT UNSIGNED AUTO_INCREMENT,
    code SMALLINT UNSIGNED,
    nombre VARCHAR(100) UNIQUE NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_nivel_nombre (nombre)
);

/* -- Currently inserting via BEFORE trigger on job_offer_snapshot
INSERT INTO nivel (
    nombre
) VALUES
    ('Asesor'), ('Asistencial'),
    ('Auxiliar'), ('Bombero Aeronáutico'),
    ('Controlador de Tránsito Aéreo'),
    ('Directivo'), ('Directivo Docente'),
    ('Docente'), ('Docente de Aula'),
    ('Docente líder de apoyo'), ('Ejecutivo'),
    ('Especialista Aeronáutico'), ('Inspector de la Aviación Civil'),
    ('Instructor'), ('Orientador de Defensa o Espiritual'),
    ('Profesional'), ('Profesional Aeronáutico'),
    ('Técnico'), ('Técnico Aeronáutico');
*/
GRANT SELECT ON {{dbname}}.nivel TO 'public'@'{{servername}}';
