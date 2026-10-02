# Service accounts & read replica

MariaDB users/grants are not provisioned by `ema` (which creates instances and
schema only). They are this repo's policy, declared in the shared
`pkg/roles-<GUID>` package (role definitions, the `sources`/`accounts`
mapping, the `allowlist` of accounts the drop pass must never remove, and the
`require` clause every account is created with) and
the per-database `pkg/<db>.roles-<GUID>` grant packages, then reconciled by the
framework's `gen-service-accounts` CLI (shipped via Composer to `vendor/bin`).
The reconcile is closed-world on **role memberships**: the desired state per
account per host is the union of the roles for that host's sources; excess roles
and any direct (non-role) grants are revoked, and undeclared accounts/roles are
dropped. See the framework's
[doc/system/service-accounts.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/service-accounts.md) for the full contract.

## Quick setup

```bash
# from the dev machine, with the database's [<name>] section recorded, the CA in
# place on the host (etc/ema.conf ssl-ca) and this machine's client certificate
# installed (see machine-certs.md)
gen-service-accounts <name> -n   # review the SQL
gen-service-accounts <name>      # apply
```

The reconcile runs from the dev machine — the machine that holds the roster
(`etc/team.ini` for `member`, `etc/machines.ini` for the tag sources) — and
reaches the host carrying the database's `db:<name>` tag as `root` over ssh,
where the deployed repo's own `ema` applies the SQL over the instance's socket.
No account of its own is needed: the connection is the host's `root`. The
roster stays here, which is why neither `etc/team.ini` nor `etc/machines.ini`
is ever shipped to a host.

A single account, passwordless and gated by `REQUIRE X509`. Each source maps to
a role; a host carrying a tag
gets the corresponding role, and a host carrying several tags gets the union:

| Source   | Role               | `<primary>`    | `<replica>` |
|----------|--------------------|----------------|-------------|
| `member` | `<account>_member` | ALL PRIVILEGES | SELECT      |
| `worker` | `<account>_worker` | ALL PRIVILEGES | SELECT      |
| `web`    | `<account>_web`    | —              | SELECT      |

`member` resolves to the `etc/team.ini` member IPs; `worker` and `web` are
`etc/machines.ini` bare tags matched exactly. A `db:<name>` tag is a
provisioning marker only — it pins no role. A role with no grant on a database
means the account is not wanted there, so `<account>_web` being absent from
`<primary>` drops `<account>@<ip>` on `<primary>` for a web host. Privileges
therefore follow what a host runs (`worker`, `web`) — never the database it
happens to store, so a host hosting the read replica cannot write the primary.

## Authentication

The security boundary has three layers, and all of them must hold for a
connection to be accepted:

1. **Overlay membership** — every operational address is a ZeroTier IP, so
   nothing outside the network can reach the instance.
2. **Source-IP host pin** — `'<account>'@'<ip>'`, one row per host, derived by
   `gen-service-accounts` from `etc/team.ini` / `etc/machines.ini`. The account
   name is not a credential: the pin is the whole identity.
3. **Client certificate** — `require: 'X509'` in the roles package makes
   `gen-service-accounts` emit `REQUIRE X509` on every account it creates or
   alters, so the connecting machine must present a certificate signed by the
   CA the instance trusts (the host-level `ssl-ca` in `etc/ema.conf`). The
   account stays passwordless — the certificate is the condition under which a
   passwordless account may connect, not a replacement credential.

Layer 3 covers what the pin cannot: the pin is satisfied by any peer on the
trusted network that can claim the address, while a certificate requires a
private key that never leaves its machine. It is a **membership gate, not
identity** — MySQL checks only that the certificate chains to a trusted CA, not
whose it is; the certificate's CN is the machine's pinned name (`etc/team.ini`
hostname) and serves as the audit trail. Issuing, installing and rotating the
certificates is documented in [machine-certs.md](machine-certs.md).

Routing: the website (`public/index.php`, `public/insight.php`) reads from
`<replica>` via `<account>`; the indexer and pipeline agents write to
`<primary>` via `<account>`. The `replication` transport account (used only by
the replica's replication thread) is created by `vendor/bin/replica-bootstrap`,
which the operator runs from the dev machine against the primary; it is
declared in the roles package `allowlist` so the reconcile never drops it, and
it stays passwordless and host-pinned to the replica host — it is not certed.
