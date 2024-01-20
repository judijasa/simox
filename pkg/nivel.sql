/* To DROP a parent tbl, first you need to DROP its child tbls */
DROP TABLE IF EXISTS job_offer;
CREATE OR REPLACE TABLE nivel (
    id SMALLINT AUTO_INCREMENT,
    nombre VARCHAR(100) UNIQUE NOT NULL,

    PRIMARY KEY(id)
);

/*
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
GRANT SELECT ON simo.nivel TO 'public'@'127.0.0.1';
