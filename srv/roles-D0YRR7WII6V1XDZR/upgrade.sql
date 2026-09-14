-- Shared simox role definitions (instance-level, one copy per team).
-- gen-service-accounts applies this before the per-database grant package, so
-- CREATE ROLE precedes the per-database GRANTs. Role assignment to accounts is
-- done by gen-service-accounts (GRANT <role> TO 'simox'@'<host>'), never
-- hardcoded here.
CREATE ROLE IF NOT EXISTS simox_member;
CREATE ROLE IF NOT EXISTS simox_worker;
CREATE ROLE IF NOT EXISTS simox_db;
CREATE ROLE IF NOT EXISTS simox_web;
