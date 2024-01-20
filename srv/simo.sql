SET check_constraint_checks = OFF;
CREATE OR REPLACE DATABASE simo
COMMENT 'Ofertas de trabajo de la plataforma SIMO del Gobierno de Colombia'
CHARACTER SET = 'utf8'
COLLATE = 'utf8_spanish_ci';

DROP USER IF EXISTS 'public'@'127.0.0.1';
CREATE USER 'public'@'127.0.0.1' IDENTIFIED BY '';

DROP USER IF EXISTS 'reader'@'127.0.0.1';
CREATE USER 'reader'@'127.0.0.1' IDENTIFIED BY 'hiroXim9';

GRANT SELECT ON simo.* TO 'reader'@'127.0.0.1';
