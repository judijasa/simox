<?php
// Shared simox role definitions (instance-level, one copy per team). Consumed
// by the framework gen-service-accounts before the per-instance grant package.
// Nothing else lives here: the SQL is in upgrade.sql.
//
// Service-account declaration (consumed by gen-service-accounts). Every key is
// consumer data — no account name, role name or source is hardcoded in the
// framework:
//
//   return new \Ema\Config\PackageConfig(
//       roles: new \Ema\Config\RolesConfig(
//           sources:   ['member' => 'simox_member', 'worker' => 'simox_worker'],
//           accounts:  ['simox' => ['member', 'worker']],
//           allowlist: ['replication'],
//           require:   'X509',
//       ),
//   );
//
// `sources` maps a source to the role it grants: `member` resolves to the
// etc/team.ini IPs; any other key is an etc/machines.ini bare tag
// (`worker`, `web`), matched exactly. A `db:<name>` tag is a provisioning
// marker only — it pins no role, because access follows what a host runs
// (`worker`, `web`) or who its members are, never what database it stores.
// `accounts` maps an account name to the sources whose hosts it is pinned to;
// its per-host roles are the union of those sources' roles.
//
// `allowlist` names accounts the closed-world drop must never remove. The
// framework keeps an always-on floor of the engine-internal `root` and
// `mariadb.sys`; `replication` is declared here explicitly because this repo's
// own replica-bootstrap creates it on the primary (not the framework), so its
// protection is visible in the declaration rather than only implicit. The
// declared list extends, never replaces, the floor.
//
// `require` is the TLS/certificate clause emitted as `REQUIRE <value>` on every
// account this reconcile creates or alters — here `X509`, so the passwordless
// account additionally demands a client certificate signed by the CA the
// instance verifies against (the host-level `ssl-ca` in etc/ema.conf). The
// source-IP pin alone is forgeable by any peer on the trusted network; a
// private key is not. Identity stays the pin (`'simox'@'<ip>'`) plus the cert
// subject recorded in the audit log — `REQUIRE X509` is a membership gate, not
// a subject match, so the same cert serves every account on the machine (see
// doc/system/machine-certs.md).
return new \Ema\Config\PackageConfig(
    roles: new \Ema\Config\RolesConfig(
        sources: [
            'member' => 'simox_member',
            'worker' => 'simox_worker',
            'web'    => 'simox_web',
        ],
        accounts: [
            'simox' => ['member', 'worker', 'web'],
        ],
        allowlist: ['replication'],
        require: 'X509',
    ),
);
