-- data taken from empleo.denominacion.nivel
CREATE OR REPLACE TABLE nivel (
    id SMALLINT UNSIGNED AUTO_INCREMENT,
    code SMALLINT UNSIGNED,
    nombre VARCHAR(100) UNIQUE NOT NULL,

    PRIMARY KEY pk_nivel_id(id),
    UNIQUE KEY uk_nivel_code(code)
);

GRANT SELECT ON {{dbname}}.nivel TO 'public'@'{{servername}}';
