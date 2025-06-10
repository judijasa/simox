/* To DROP a parent tbl, first you need to DROP its child tbls */
CREATE OR REPLACE TABLE alternativa (
    id INT,
    simo_id INT,

    PRIMARY KEY(id) -- TODO: Complete this schema
);

GRANT SELECT ON {{dbname}}.alternativa TO 'public'@'{{servername}}';
