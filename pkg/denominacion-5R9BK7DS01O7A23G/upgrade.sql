/*
To DROP a parent tbl, first you need to DROP its child tbls,
that is why we make sure job_offer is dropped before
replacing the denominacion table.
*/
CREATE OR REPLACE TABLE denominacion (
    id INT AUTO_INCREMENT,
    code INT NOT NULL,  -- id at simo
    nivel VARCHAR(100),
    nombre VARCHAR(10000) NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_denominacion_code (code)
);

GRANT SELECT ON {{dbname}}.denominacion TO 'public'@'{{servername}}';
