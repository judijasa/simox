# Service accounts & read replica

MariaDB users/grants are not provisioned by `ema` (which creates instances and
schema only). They are this repo's policy, declared in the shared
`srv/roles-<GUID>` package (role definitions, the `$sources`/`$accounts`
mapping, and the `$allowlist` of accounts the drop pass must never remove) and
the per-database `srv/<db>.roles-<GUID>` grant packages, then reconciled by the
framework's `gen-service-accounts` CLI (shipped via Composer to `vendor/bin`).
The reconcile is closed-world on **role memberships**: the desired state per
account per host is the union of the roles for that host's sources; excess roles
and any direct (non-role) grants are revoked, and undeclared accounts/roles are
dropped. See the framework's `doc/system/service-accounts.md` for the full
contract.

A single account, passwordless — the security boundary is ZeroTier membership
plus the source-IP host pin. Each source maps to a role; a host carrying a tag
gets the corresponding role, and a host carrying several tags gets the union:

| Source   | Role           | `simo0`        | `simo1` |
|----------|----------------|----------------|---------|
| `member` | `simox_member` | ALL PRIVILEGES | SELECT  |
| `worker` | `simox_worker` | ALL PRIVILEGES | SELECT  |
| `web`    | `simox_web`    | —              | SELECT  |

`member` resolves to the `etc/team.ini` member IPs; `worker` and `web` are
`etc/machines.ini` `[prod]` bare tags matched exactly. A `db:<name>` tag is a
provisioning marker only — it pins no role. A role with no grant on a database
means the account is not wanted there, so `simox_web` being absent from `simo0`
drops `simox@<ip>` on `simo0` for a web host. Privileges therefore follow what
a host runs (`worker`, `web`) — never the database it happens to store, so a
host hosting the read replica cannot write the primary.

Routing: the website (`public/index.php`, `public/insight.php`) reads from
`simo1` via `simox`; the indexer and pipeline agents write to `simo0` via
`simox`. The `replication` transport account (used only by the replica's
replication thread) is created by the replica bootstrap on the primary; it is
declared in the roles package `$allowlist` so the reconcile never drops it.
