<?php
// Default database definition for 'simo1' (the read-only replica). The
// schema arrives from the primary via replication, never from a builder, so
// there is no $dependencies here (and no upgrade.sql): ema's replica build
// restores the primary's shipped snapshot and attaches to 'simo0' over the
// low-priv 'replication' account (see doc/system/replica-bootstrap.md).
$db = array(
    'dbname' => 'simo1',
    'type' => 'replica',
    'replica_of' => 'simo0',
    'charset' => 'utf8',
    'collation' => 'utf8_spanish_ci',
);
?>
