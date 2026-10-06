<?php
// Default database definition for the `simo1` instance (the read-only
// replica), serving the primary's `simo` schema. The schema arrives from the
// primary via replication, never from a builder, so there are no dependencies
// here (and no upgrade.sql): ema's replica build restores the primary's
// shipped snapshot and attaches to the `simo0` instance over the low-priv
// 'replication' account (see doc/system/replica-bootstrap.md).
return new \Ema\Config\DatabaseConfig(
    dbname: 'simo',
    type: 'replica',
    replica_of: 'simo0',
    replica_ssl_verify_server_cert: false, // replica only: verify the primary's server certificate (off: self-signed cert, no CA yet)
);
