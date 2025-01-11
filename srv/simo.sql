SET check_constraint_checks = OFF;
DROP DATABASE IF EXISTS simo; -- To avoid foreign key error (not sure why)
CREATE OR REPLACE DATABASE {{dbname}}
COMMENT 'Ofertas de trabajo de la plataforma SIMO del Gobierno de Colombia'
CHARACTER SET = 'utf8'
COLLATE = 'utf8_spanish_ci';

DROP USER IF EXISTS 'admin'@'{{servername}}';
CREATE USER 'admin'@'{{servername}}' IDENTIFIED BY '{{admin_password}}';

DROP USER IF EXISTS 'reader'@{{servername}};
CREATE USER 'reader'@'{{servername}}' IDENTIFIED BY '{{reader_password}}';

DROP USER IF EXISTS 'public'@'{{servername}}';
CREATE USER 'public'@'{{servername}}' IDENTIFIED BY '';

GRANT SELECT ON simo.* TO 'reader'@'{{servername}}';
