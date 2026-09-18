-- Shared simox role definitions (instance-level, one copy per team).
-- gen-service-accounts applies this before the per-database grant package, so
-- CREATE ROLE precedes the per-database GRANTs. Role assignment to accounts is
-- done by gen-service-accounts (GRANT <role> TO 'simox'@'<host>'), never
-- hardcoded here.
--
-- One `db` role per database: each is granted only on its own database (see
-- the per-database grant packages), so a host tagged `db:simo1` holds no
-- privilege at all on `simo0`.
CREATE ROLE IF NOT EXISTS simox_member;
CREATE ROLE IF NOT EXISTS simox_worker;
CREATE ROLE IF NOT EXISTS simox_db_simo0;
CREATE ROLE IF NOT EXISTS simox_db_simo1;
CREATE ROLE IF NOT EXISTS simox_web;
