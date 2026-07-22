/* To DROP a parent tbl, first you need to DROP its child tbls */
CREATE OR REPLACE TABLE estudio (
    id INT AUTO_INCREMENT,
    descripcion TEXT,
    -- TODO: add cols that are synthesis of descr

    PRIMARY KEY(id)
);

GRANT SELECT ON {{dbname}}.estudio TO 'public'@'{{servername}}';
