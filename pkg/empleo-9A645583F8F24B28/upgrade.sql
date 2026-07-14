CREATE OR REPLACE TABLE empleo (
    id INT AUTO_INCREMENT,
    opec INT NOT NULL,
    created_date DATETIME, -- assigned by simo
    asignacion_salarial VARCHAR(50),
    codigo_empleo VARCHAR(10), -- assigned by simo
    sin_codigo BOOL,
    denominacion_id INT,
    grado_nivel JSON,
    grado_denominacion JSON,
    convocatoria_id INT,
    funcion_id INT,
    requisito_id INT,
    vacante_id INT,
    area JSON,
    discapacidades JSON,
    documento_id INT,
    entidad_id SMALLINT,
    identificador VARCHAR(100),
    vigencia_salarial YEAR,
    urbano BOOL,
    aeronautico BOOL,
    no_cobro_opec BOOL,
    estado_inscripcion VARCHAR(100),
    favorito BOOL,
    inscripcion_id JSON,
    fecha_inscripcion VARCHAR(100),
    nivel_nombre VARCHAR(100),
    `access` JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY(id),
    UNIQUE INDEX(opec),
    INDEX ix_empleo_created_at(created_at),
    INDEX ix_empleo_cierre(cierre),
    /* You can't make a foreign key from a col
    that's not unique in its parent tbl*/
    FOREIGN KEY fk_empleo_nivel_id(nivel_id)
        REFERENCES nivel(id),
        -- ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_empleo_denominacion_id(denominacion_id)
        REFERENCES denominacion(id),
        -- ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_empleo_requisito_id(requisito_id)
        REFERENCES requisito(id),
        -- ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_empleo_vacante_id(vacante_id)
        REFERENCES vacante(id),
        -- ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY fk_empleo_max_snap_id(max_snap_id)
        REFERENCES empleo_snapshot(id)
        -- ON UPDATE CASCADE ON DELETE CASCADE
);

GRANT SELECT ON {{dbname}}.empleo TO 'public'@'{{servername}}';
