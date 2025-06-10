/* To DROP a parent tbl, first you need to DROP its child tbls */
CREATE OR REPLACE TABLE requisito (
    id INT AUTO_INCREMENT,
    simo_id INT,
    opec INT UNIQUE NOT NULL,
    estudio_id INT,
    experiencia_id INT,
    otros_id INT,
    alternativa_id INT,
    equivalencias JSON,

    PRIMARY KEY(id),
    FOREIGN KEY fk_requisito_estudio_id(estudio_id)
      REFERENCES estudio(id),
    FOREIGN KEY fk_requisito_experiencia_id(experiencia_id)
      REFERENCES experiencia(id),
    FOREIGN KEY fk_requisito_otros_id(otros_id)
      REFERENCES otros(id),
    FOREIGN KEY fk_requisito_alternativa_id(alternativa_id)
      REFERENCES alternativa(id)
);

GRANT SELECT ON {{dbname}}.requisito TO 'public'@'{{servername}}';

-- We make this table instead of adding these cols directly
-- on job_offer because it allows many rows associated
-- to the same opec
