<?php
// Shared simox role definitions (instance-level, one copy per team). Consumed
// by the framework gen-service-accounts before the per-database grant package.
// Nothing else lives here: the SQL is in upgrade.sql.
//
// Service-account declaration (consumed by gen-service-accounts). Every key is
// consumer data — no account name, role name or source is hardcoded in the
// framework:
//
//   $sources  = array('member' => 'simox_member', 'worker' => 'simox_worker');
//   $accounts = array('simox' => array('member', 'worker'));
//
// $sources maps a source to the role it grants: `member` resolves to the
// etc/team.ini IPs; any other key is an etc/machines.ini [prod] tag (bare, or
// db:<name>), matched exactly. $accounts maps an account name to the sources
// whose hosts it is pinned to; its per-host roles are the union of those
// sources' roles. root, mariadb.sys and replication are always protected from
// the closed-world drop (see the framework doc/system/service-accounts.md).
$dependencies = array();

$sources = array(
    'member'   => 'simox_member',
    'worker'   => 'simox_worker',
    'db:simo0' => 'simox_db',
    'db:simo1' => 'simox_db',
    'web'      => 'simox_web',
);

$accounts = array(
    'simox' => array('member', 'worker', 'db:simo0', 'db:simo1', 'web'),
);
?>
