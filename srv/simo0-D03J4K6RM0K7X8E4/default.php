<?php
// Default database definition for 'simo0' (the writable primary). Non-secret
// defaults shared by dev and prod (the connection file carries the endpoint).
// ema creates schema only - users/grants are consumer policy and never live
// here (see pkg/roles-<GUID> + pkg/simo0.roles-<GUID>, reconciled by the
// framework gen-service-accounts).
return new \Ema\Config\DatabaseConfig(
    dbname: 'simo0',
    binlog: true,               // primary only: binary logging (replica source, PITR, CDC)
    // binlog_expire_days: 7,   // retention (days); only applies with 'binlog'
    dependencies: [
        'simo-C196A24801D24B16',
    ],
);
