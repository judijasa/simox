CREATE OR REPLACE TABLE job_offer_location (
    id INT AUTO_INCREMENT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opec INT NOT NULL,
    location_id SMALLINT UNSIGNED, -- id at simo
    departamento_id TINYINT, -- in case of dpto but no municipio

    PRIMARY KEY(id),
    UNIQUE KEY (opec, location_id),
    -- order of composite pkey matters

    -- opec can't be fk because ref tbl not yet populated
    -- FOREIGN KEY fk_job_offer_location_opec(opec) REFERENCES job_offer(opec),
    -- location_code can't be fk because ref tbl not yet populated.
    -- FOREIGN KEY fk_job_offer_location_location_code(municipio_code) REFERENCES location(code),
    FOREIGN KEY fk_job_offer_location_departamento_id(departamento_id) REFERENCES departamento(id)
);
