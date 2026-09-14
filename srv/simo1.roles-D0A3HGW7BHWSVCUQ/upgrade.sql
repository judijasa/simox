-- Per-database grants for `simo1` (the read-only replica): privileges granted
-- to the shared roles. {{dbname}} is filled by gen-service-accounts from the
-- target database.
GRANT SELECT ON {{dbname}}.* TO simox_member;
GRANT SELECT ON {{dbname}}.* TO simox_worker;
GRANT SELECT ON {{dbname}}.* TO simox_db;
GRANT SELECT ON {{dbname}}.* TO simox_web;
