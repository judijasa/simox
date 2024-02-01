CREATE OR REPLACE TABLE estudio_basico_variaciones (
    id INT AUTO_INCREMENT,
    nombre VARCHAR(75) UNIQUE NOT NULL,
    estudio_basico_id INT,

    PRIMARY KEY(id),
    CONSTRAINT estudio_basico_variaciones_estudio_basico_id_fk
    FOREIGN KEY(estudio_basico_id)
        REFERENCES estudio_basico(id)
);

INSERT INTO estudio_basico_variaciones ( /* initial dump */
    nombre
) VALUES
    ('Administración'), ('Administrativo'), ('Administrativa'),
    ('Agronómica'), ('Agronomía'), ('Alimentos'),
    ('Archivística'), ('Arquitectura'), ('Antropología'),
    ('Bacteriología'), ('Bibliotecología'), ('Biología'),
    ('Civil'), ('Constitucional'), ('Contaduría'),
    ('Derecho'), ('Deportes'), ('Diseño'),
    ('Economía'), ('Educación'), ('Eléctrica'),
    ('Electrónica'), ('Enfermería'), ('Estadística'),
    ('Familia'), ('Filosofía'), ('Forestal'),
    ('Geografía'), ('Historia'), ('Ingeniería'),
    ('Informática'), ('Industrial'), ('Justicia'),
    ('Leyes'), ('Medicina'), ('Mecánica'),
    ('Literatura'), ('Lingüística'), ('Lingüista'),
    ('Matemáticas'), ('Matemática'), ('Metalurgia'),
    ('Microbiología'), ('Fisioterapia'), ('Odontología'),
    ('Periodismo'), ('Periodista'), ('Pecuaria'),
    ('Publicidad'), ('Publicista'), ('Procesal'),
    ('Pública'), ('Psicología'), ('Politólogo'),
    ('Química'), ('Sanitaria'), ('Sistemas'),
    ('Sociología'), ('Telemática'), ('Telecomunicaciones'),
    ('Teología'), ('Urbanismo'), ('Urbanista'),
    ('Veterinaria'), ('Zootecnia'), ('Ingeniería Civil'),
    ('Ingeniería Industrial'), ('Ingeniería Mecánica'), ('Ingeniería de Sistemas'),
    ('Ingeniería Eléctrica'), ('Ingeniería Electrónica'), ('Ingeniería Informática'),
    ('Ingeniería de Sistemas e Informática'), ('Ingeniería de Petróleos'), ('Ingeniería de Minas'),
    ('Ingeniería Química'), ('Ingeniería Agronómica'), ('Ingeniería Agrícola'),
    ('Ingeniería Agroindustrial'), ('Ingeniería Forestal'), ('Ingeniería de Alimentos'),
    ('Ingeniería de Calidad'), ('Ingeniería en Calidad'), ('Ingeniería Ambiental'),
    ('Ingeniería Sanitaria'), ('Ingeniería del Desarrollo Ambiental'), ('Ingeniería Administrativa'),
    ('Ingeniería Financiera'), ('Ingeniería Financiera y de Negocios'), ('Ingeniería Administrativa y de Finanzas'),
    ('Ingeniería en Seguridad Industrial'), ('Ingeniería Catastral y Geodesia'),('Higiene Ocupacional'),
    ('Bibliotecología y Archivología'), ('Ciencias de la Información y de la Documentación'), ('Ciencias de la Información y la Documentación'),
    ('Ciencias Agronómicas'), ('Ciencia Agronómica'), ('Ciencias Administrativas'),
    ('Ciencias Sociales'), ('Ciencias Humanas'), ('Ciencias Sociales y Humanas'),
    ('Ciencia Política'), ('Ciencia de la Información'), ('Ciencias Políticas'),
    ('Sistemas de Información'), ('Salud Pública'), ('Salud Ocupacional'),
    ('Medicina Veterinaria'), ('Economía y Finanzas'), ('Economía y Desarrollo'),
    ('Laboratorio Clínico'), ('Trabajo Social'), ('Comunicación Social'),
    ('Artes Liberales'), ('Justicia y Derecho'), ('Bibliotecología y Archivística'),
    ('Leyes y Jurisprudencia'), ('Física y Recreación'), ('Educación Física y Recreación'),
    ('Lenguas Modernas');
