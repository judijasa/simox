CREATE OR REPLACE TABLE empleo_snapshot (
    id INT AUTO_INCREMENT,
    opec INT NOT NULL,
    empleo JSON,
    estado_inscripcion VARCHAR(100),
    favorito BOOLEAN,
    inscripcion_id JSON,
    fecha_inscripcion VARCHAR(100) NOT NULL DEFAULT '',
    nivel_nombre VARCHAR(100),
    `access` JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY empleo_snapshot_id(id),
    UNIQUE KEY ux_empleo_snapshot_opec_fecha (opec, fecha_inscripcion)
);
