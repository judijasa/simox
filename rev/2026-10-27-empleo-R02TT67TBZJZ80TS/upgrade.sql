ALTER TABLE empleo
    ADD COLUMN opec_historico BOOL NOT NULL DEFAULT 0,
    ADD INDEX ix_empleo_opec_historico (opec_historico);
