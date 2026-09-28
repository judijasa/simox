-- Per-database grants for `simo0` (the writable primary): privileges granted
-- to the shared roles. {{dbname}} is filled by gen-service-accounts from the
-- target database. `simox_web` is deliberately absent: web is read-only on
-- `simo1`, so a host tagged `web` holds no role here and its `simox` account
-- is dropped on `simo0`.
GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_member;
GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_worker;
