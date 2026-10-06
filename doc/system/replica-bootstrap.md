# Read-replica bootstrap (`<primary>` -> `<replica>`)

How `<replica>` is created as the read-only replica of the writable primary
`<primary>`. The replica serves the website (`<account>`) and can offload reads
from `<primary>`. Naming convention (a consumer choice): the primary and its
replica are two *instances* that share the primary's schema under a plain name
— the examples below use the `0`-suffixed `<primary>` and `1`-suffixed
`<replica>` pair, both serving the same `<schema>`.

Two operator steps, both run from this repo's root: the framework's
`replica-bootstrap` CLI prepares the transport account and a snapshot on
`<primary>`, then `ema create` builds the replica on `<replica>`. The mechanism
is upstream — this document carries only the consumer-side policy:

- the CLI — flags, the `etc/reuter.ini` lookup, snapshot + coordinate, the
  primary's host requirements: the framework's
  [doc/system/replica-bootstrap.md](https://github.com/judijasa/php_daas_framework/blob/main/doc/system/replica-bootstrap.md);
- the build — `type=replica` packages, `--from-snapshot`, the gates it
  enforces: `ema`'s
  [doc/system/replica-bootstrap.md](https://github.com/judijasa/ema/blob/main/doc/system/replica-bootstrap.md).

## Quick setup

```bash
# primary (<primary>): enable binary logging (log_bin) in its instance my.cnf
#                      and restart it
# both hosts: install the MariaDB backup package (mariabackup)

# from this repo's root
vendor/bin/pf-host <replica>                 # -> <replica-ip>
vendor/bin/replica-bootstrap --primary <primary> --replica-host <replica-ip>

# on <replica>, inside a tmux-remote session
ema create srv/<replica>-<GUID> --from-snapshot /root/replica-snapshot-<primary>

# then record the printed [<replica>] section in the private etc/reuter.ini
```

## What this repo owns

1. **A live `<primary>`** with its schema applied (`ema create
   srv/<primary>-<GUID>`).
2. **The `srv/<replica>-<GUID>` package** — `type=replica`,
   `replica_of=<primary>`, and no `dependencies`/`upgrade.sql`: `<replica>`'s
   schema arrives from the primary via replication, never from a schema
   builder. It sets `replica_ssl_verify_server_cert: false` explicitly, so ema
   emits `MASTER_SSL_VERIFY_SERVER_CERT=0` — verification stays off while
   the primary's certificate is the self-signed one; flip it to `true` once
   a CA is provisioned.
3. **The `replication` account's allowlist entry.** The account is created by
   the bootstrap CLI, but it is deliberately **not** part of the
   `sources`/`accounts` service-account declaration: the reconcile grants it
   no roles and must not manage its `*.*` grant. It *is* declared in the package
   `allowlist` so the closed-world drop pass keeps it (the framework's drop
   floor is only `root`/`mariadb.sys`).
4. **`<primary>`'s host prerequisites.** Two prerequisites must hold before the
   run: the MariaDB backup package (`mariabackup`) installed on `<primary>`, and
   binary logging (`log_bin`) enabled on `<primary>`. The bootstrap checks only
   the first — it aborts with an `ERROR:` line if `mariabackup` is missing. It
   does **not** check binlog: verify it manually, or the snapshot ships with
   no binlog coordinate and `ema create` fails on the replica host instead. A
   miss leaves no partial state, so the run is safe to repeat.

## Run it

```bash
vendor/bin/pf-host <replica>                 # the replica's ZeroTier IP
vendor/bin/replica-bootstrap --primary <primary> --replica-host <replica-ip> --dry-run
vendor/bin/replica-bootstrap --primary <primary> --replica-host <replica-ip>
```

`--replica-host` takes the literal IP `pf-host` prints, not a name: the CLI uses
it both as the `root@<ip>` SSH/scp target and as the `'replication'@'<ip>'` host
pin, which must be the address `<primary>` sees as the replica's client source
(the framework's CLI doc has the full argument).

Then build the replica on `<replica>` — inside a `tmux-remote` session, like any
database creation ([add-database.md](add-database.md)):

```bash
ema create srv/<replica>-<GUID> --from-snapshot /root/replica-snapshot-<primary>
```

## After the build

Record `<replica>`'s `[<replica>]` section in the private `etc/reuter.ini` — the
section `ema create` printed, or `ema values <replica>` if it was lost. The
section header names the instance; its `DBNAME` key names the shared `<schema>`
both instances serve. The website reads `<replica>`, not `<primary>`; both
sections carry the single `<account>` password key (`<account>` is ALL on
`<primary>` and SELECT on `<replica>`).
