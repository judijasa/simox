/* To DROP a parent tbl, first you need to DROP its child tbls */
CREATE OR REPLACE TABLE estudio (
    id INT,
    descripcion TEXT,
    -- TODO: add cols that are synthesis of descr

    PRIMARY KEY(id) -- TODO: Complete this schema
);

GRANT SELECT ON {{dbname}}.estudio TO 'public'@'{{servername}}';
