-- simo database bootstrap, consumed by
--   ema sandbox srv/simo-D03J4K6RM0K7X8E4 (dev) and
--   ema create srv/simo-D03J4K6RM0K7X8E4 (prod). Placeholders are filled from
-- default.php defaults: {{dbname}}, {{charset}}, {{collation}}.
SET check_constraint_checks = OFF;
DROP DATABASE IF EXISTS {{dbname}}; -- To avoid foreign key error (not sure why)
CREATE OR REPLACE DATABASE {{dbname}}
COMMENT 'Ofertas de trabajo de la plataforma SIMO del Gobierno de Colombia'
CHARACTER SET = '{{charset}}'
COLLATE = '{{collation}}';
