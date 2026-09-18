-- Per-database grants for `simo1` (the read-only replica): privileges granted
-- to the shared roles. {{dbname}} is filled by gen-service-accounts from the
-- target database. `simox_db_simo0` is deliberately absent: each `db` role is
-- granted only on its own database.
GRANT SELECT ON {{dbname}}.* TO simox_member;
GRANT SELECT ON {{dbname}}.* TO simox_worker;
GRANT SELECT ON {{dbname}}.* TO simox_db_simo1;
GRANT SELECT ON {{dbname}}.* TO simox_web;
