<?php
// Default database definition for 'simo0' (the writable primary). Non-secret
// defaults shared by dev and prod (the connection file carries the endpoint).
// ema creates schema only - users/grants are consumer policy and never live
// here (see srv/roles-<GUID> + srv/simo0.roles-<GUID>, reconciled by the
// framework gen-service-accounts).
$db = array(
    'dbname' => 'simo0',
    'charset' => 'utf8',
    'collation' => 'utf8_spanish_ci',
);
// Schema packages this database applies (pkg/<name>-<GUID>), dependency order.
$dependencies = array(
    'simo-C196A24801D24B16',
);
?>
