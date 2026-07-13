CREATE OR REPLACE TABLE convocatoria (
    id INT AUTO_INCREMENT,
    codigo VARCHAR(10), -- provided by simo source website
    nombre VARCHAR(250),
    agno YEAR,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY(id),
    UNIQUE INDEX idx_convocatoria_codigo(codigo),
    INDEX idx_job_offer_agno(agno)
);

GRANT SELECT ON {{dbname}}.job_offer TO 'public'@'{{servername}}';
