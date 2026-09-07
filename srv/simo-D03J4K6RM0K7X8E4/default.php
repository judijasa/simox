<?php
// Default database definition for 'simo'. Non-secret defaults shared by dev
// and prod (the connection file carries the endpoint). ema creates schema
// only - users/grants are consumer policy and never live here.
$db = array(
    'dbname' => 'simo',
    'charset' => 'utf8',
    'collation' => 'utf8_spanish_ci',
);
// Schema packages this database applies (pkg/<name>-<GUID>), dependency order.
$dependencies = array(
    'simo-C196A24801D24B16',
);
?>
