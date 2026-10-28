CREATE OR REPLACE SEQUENCE cnsc_aviso_snapshot_id_seq AS INT CACHE 50;

CREATE OR REPLACE TABLE cnsc_aviso_snapshot (
    id INT,
    node_id INT NOT NULL,
    aviso JSON,
    titulo VARCHAR(500),
    fecha_publicacion DATETIME,
    cuerpo MEDIUMTEXT,
    convocatoria JSON,
    documentos JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY pk_cnsc_aviso_snapshot_id(id),
    UNIQUE KEY uk_cnsc_aviso_snapshot_node_id(node_id)
);
