-- Per-instance grants for `simo0` (the writable primary instance): privileges
-- granted to the shared roles on the `simo` schema. {{dbname}} is filled by
-- gen-service-accounts from the package's `dbname` (the schema). `simox_web`
-- is deliberately absent: web is read-only on `simo1`, so a host tagged `web`
-- holds no role here and its `simox` account is dropped on `simo0`.
GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_member;
GRANT ALL PRIVILEGES ON {{dbname}}.* TO simox_worker;
