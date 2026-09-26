# Read-replica bootstrap (simo0 -> simo1)

How `simo1` is created as the read-only replica of the writable primary
`simo0`. The replica serves the website (`simox` account) and can offload
reads from `simox`. Naming convention: the primary is `0`-suffixed (`simo0`),
its replica `1`-suffixed (`simo1`).

Building it is two operator steps: the framework's `replica-bootstrap` CLI
prepares the transport account and a snapshot on the primary, then `ema create`
builds the replica instance from that snapshot on the replica host.

## Preconditions (the build fails loudly if any is unmet)

`ema create` refuses to build the replica unless all three hold. Only the first
is provisioned by hand; the other two are the *output* of the bootstrap step
below — they are preconditions for the build, not manual preparation.

1. A live `simo0` instance with its schema applied (`ema create
   srv/simo0-D03J4K6RM0K7X8E4`).
2. A `replication` transport account on the primary, granted
   `REPLICATION SLAVE`. It is deliberately **not** part of the
   `$sources`/`$accounts` service-account declaration — the reconcile grants it
   no roles and must not manage its `*.*` grant. It *is* declared in the
   package `$allowlist` so the closed-world drop pass keeps it (the
   framework's drop floor is only `root`/`mariadb.sys`).
3. A snapshot of `simo0` to restore on `simo1`, plus its replication
   coordinate (see below).

The replica build refuses to proceed if the snapshot is missing or unreadable,
its coordinate is absent, or the `replication` account is missing/wrong.

## Running the bootstrap

One-time and operator-run, from this repo's root on a dev machine with root SSH
to both hosts (ZeroTier). The CLI is a Composer-installed framework script
(`vendor/bin/replica-bootstrap`, like the other framework CLIs); it orchestrates
the primary and the replica over SSH itself, so it is never run *on* either
host:

```bash
vendor/bin/replica-bootstrap --primary simo0 --replica-host <simo1-ip>
vendor/bin/replica-bootstrap --primary simo0 --replica-host <simo1-ip> --dry-run
```

- `--primary simo0` is a **name**: the `[simo0]` section of the private
  `etc/reuter.ini` (`etc/reuter.ini.template` documents the keys) supplies the
  primary's `SERVER` — the host, and also the SSH target — and
  `MYSQL_UNIX_PORT`, the instance's root/unix_socket. The CLI reads
  `etc/reuter.ini` from the working directory and takes no environment
  override: the bootstrap always needs prod connectivity, whereas a dev shell's
  `REUTER_INI` points at the sandbox ini.
- `--replica-host <simo1-ip>` is a literal **IP**, because the replica has no
  instance and no `[simo1]` section yet, so there is nothing to look up. The
  value is used twice — as the `root@<ip>` SSH/scp target and as the
  `'replication'@'<ip>'` host pin — and the pin must be the address the primary
  sees as the replica's client source, so it cannot be a name or an ssh alias.
  Get it from the framework's shared host lookup: `vendor/bin/pf-host simo1`
  prints the ZeroTier IP of the `[prod]` host carrying the `db:simo1` tag,
  pairing `etc/hosts` (`etc/hosts.template`) with the `[prod]` roster in
  `etc/machines.ini` (`etc/machines.ini.template`) and refusing a host outside
  `[prod]` (the framework's `doc/system/host-resolution.md`). This flag is the
  exception among host-taking CLIs — `pf-deploy.sh`, `gen-firewall` and
  `tmux-remote` take either spelling through that lookup, this one takes no
  name and needs the IP the lookup prints.
- `--dest <dir>` is the directory on the replica host the snapshot extracts
  into (default `/root/replica-snapshot-simo0` — named after the primary, not
  the replica).
- `--dry-run` prints the commands without executing them.
- Re-runnable: account creation is idempotent and the snapshot is rebuilt and
  re-shipped on every run. Nothing else runs it — not a deploy step, not cron.

## Snapshot + coordinate

The bootstrap takes a consistent `mariabackup` snapshot of `simo0` (prepared
before shipping) and records its replication coordinate — `mariabackup` is the
only snapshot format `ema create --from-snapshot` accepts. It ships the
snapshot to the replica host itself, so the replica's provisioning never holds
root on the primary.

- Concrete coordinate today: **binlog file/position**, which the replica build
  reads from the snapshot's own backup metadata (`mariadb_backup_info`, or
  `xtrabackup_info` on older tooling — the `filename '...'`/`position '...'`
  fields).
- GTID resume (`MASTER_USE_GTID = slave_pos`) is preferred and tracked as a
  follow-up, not yet implemented.

## What the replica build does

The `ema create` replica flow (the package `srv/simo1-<GUID>` is
`type=replica`, `replica_of=simo0`) does, in order:

1. require `--from-snapshot <path>` (no snapshot -> refuse to build);
2. restore the snapshot onto the `simo1` instance (no `mariadb-install-db` —
   the snapshot carries the system tables);
3. skip schema apply entirely — schema arrives from the primary via
   replication, so `simo1` has no `$dependencies` and no `upgrade.sql`;
4. set `read_only = 1`, `replicate-rewrite-db = "simo0->simo1"` and a
   distinct `server_id`;
5. configure and `START SLAVE` against `simo0` using the `replication`
   account and the recorded coordinate.

The bootstrap half (transport account + snapshot) is generic upstream — the
framework's `vendor/bin/replica-bootstrap` (see the framework's
`doc/system/replica-bootstrap.md`). This repo owns only the replica policy: the
`srv/simo1-<GUID>` package and the build command itself,
`ema create srv/simo1-<GUID> --from-snapshot /root/replica-snapshot-simo0`, run
on the replica host (`EMA_TARGET=prod`, inside a `tmux-remote` session — see
[add-database.md](add-database.md)).
