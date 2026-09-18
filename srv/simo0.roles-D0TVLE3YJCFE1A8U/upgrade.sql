-- Per-database grants for `simo0` (the writable primary): privileges granted
-- to the shared roles. {{dbname}} is filled by gen-service-accounts from the
-- target database. `simox_web` and `simox_db_simo1` are deliberately absent:
-- web is read-only on `simo1`, and the replica host's `db` role is scoped to
-- `simo1`, so a host tagged `web` or `db:simo1` (or both) holds no role here
-- and its `simox` account is dropped on `simo0`.
GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_member;
GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_worker;
GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_db_simo0;
