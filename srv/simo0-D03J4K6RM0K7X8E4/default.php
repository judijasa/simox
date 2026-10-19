<?php
// Default database definition for the `simo0` instance (the writable
// primary), serving the `simo` schema. Non-secret defaults shared by dev and
// prod (the connection file carries the endpoint). ema creates the schema
// only - users/grants are consumer policy and never live here (see
// pkg/roles-<GUID> + pkg/simo0.roles-<GUID>, reconciled by the framework
// gen-service-accounts).
return new \Ema\Config\DatabaseConfig(
    dbname: 'simo',
    charset: 'utf8mb4',         // 4-byte UTF-8: scraped bodies carry emoji
    collation: 'utf8mb4_spanish_ci',
    binlog: true,               // primary only: binary logging (replica source, PITR, CDC)
    // binlog_expire_days: 7,   // retention (days); only applies with 'binlog'
    dependencies: [
        'simo-C196A24801D24B16',
    ],
);
