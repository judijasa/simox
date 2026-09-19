<?php
// Shared simox role definitions (instance-level, one copy per team). Consumed
// by the framework gen-service-accounts before the per-database grant package.
// Nothing else lives here: the SQL is in upgrade.sql.
//
// Service-account declaration (consumed by gen-service-accounts). Every key is
// consumer data — no account name, role name or source is hardcoded in the
// framework:
//
//   $sources   = array('member' => 'simox_member', 'worker' => 'simox_worker');
//   $accounts  = array('simox' => array('member', 'worker'));
//   $allowlist = array('replication');
//
// $sources maps a source to the role it grants: `member` resolves to the
// etc/team.ini IPs; any other key is an etc/machines.ini [prod] tag (bare, or
// db:<name>), matched exactly. Each `db:<name>` tag maps to a role of its own,
// granted only on that database (see the per-database grant packages), so a
// replica host never carries write access to the primary. $accounts maps an
// account name to the sources whose hosts it is pinned to; its per-host roles
// are the union of those sources' roles.
//
// $allowlist names accounts the closed-world drop must never remove. The
// framework keeps an always-on floor of the engine-internal `root` and
// `mariadb.sys`; `replication` is declared here explicitly because this repo's
// own replica-bootstrap creates it on the primary (not the framework), so its
// protection is visible in the declaration rather than only implicit. The
// declared list extends, never replaces, the floor.
$dependencies = array();

$sources = array(
    'member'   => 'simox_member',
    'worker'   => 'simox_worker',
    'db:simo0' => 'simox_db_simo0',
    'db:simo1' => 'simox_db_simo1',
    'web'      => 'simox_web',
);

$accounts = array(
    'simox' => array('member', 'worker', 'db:simo0', 'db:simo1', 'web'),
);

$allowlist = array('replication');
?>
