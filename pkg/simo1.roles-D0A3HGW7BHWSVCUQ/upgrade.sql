-- Per-instance grants for `simo1` (the read-only replica instance):
-- privileges granted to the shared roles on the `simo` schema. {{dbname}} is
-- filled by gen-service-accounts from the package's `dbname` (the schema).
GRANT SELECT ON {{dbname}}.* TO simox_member;
GRANT SELECT ON {{dbname}}.* TO simox_worker;
GRANT SELECT ON {{dbname}}.* TO simox_web;
