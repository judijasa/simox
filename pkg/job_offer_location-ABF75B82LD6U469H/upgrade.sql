DROP TABLE IF EXISTS job_offer_location;
CREATE TABLE job_offer_location (
    opec INT,
    -- municipio_id INT DEFAULT -1,
    dpto_colombia_id TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (opec, dpto_colombia_id),
    -- order of composite pkey matters
    -- INDEX idx_job_offer_location(municipio_id),

    -- job_offer.opec can't be foreign key because that tbl is not yet populated with that opec value
    -- FOREIGN KEY fk_job_offer_location_opec(opec) REFERENCES job_offer(opec),
    -- FOREIGN KEY fk_job_offer_location_municipio_id(municipio_id) REFERENCES municipio(id),
    FOREIGN KEY fk_job_offer_location_dpto_colombia_id(dpto_colombia_id) REFERENCES dpto_colombia(id)
);
