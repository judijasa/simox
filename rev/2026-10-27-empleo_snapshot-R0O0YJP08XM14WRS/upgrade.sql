ALTER TABLE empleo_snapshot
    ADD COLUMN source ENUM('publico','historico','provision') NOT NULL DEFAULT 'publico',
    DROP KEY ux_empleo_snapshot_opec_fecha,
    ADD UNIQUE KEY ux_empleo_snapshot_opec_source_fecha (opec, source, fecha_inscripcion);
