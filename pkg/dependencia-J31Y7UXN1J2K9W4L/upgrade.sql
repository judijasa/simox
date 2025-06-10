/*
To DROP a parent tbl, first you need to DROP its child tbls,
that is why we make sure job_offer is dropped before
replacing the dependencia table.
*/
CREATE OR REPLACE TABLE dependencia (
    id INT AUTO_INCREMENT,
    code INT, -- simo id
    nombre VARCHAR(10000) UNIQUE NOT NULL,

    PRIMARY KEY(id),
    UNIQUE KEY uk_dependencia (code, nombre)
);

GRANT SELECT ON {{dbname}}.dependencia TO 'public'@'{{servername}}';
