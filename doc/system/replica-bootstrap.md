# Read-replica bootstrap (simo0 -> simo1)

How `simo1` is created as the read-only replica of the writable primary
`simo0`. The replica serves the website (`simox` account) and can offload
reads from `simox`. Naming convention: the primary is `0`-suffixed (`simo0`),
its replica `1`-suffixed (`simo1`).

## Preconditions (the build fails loudly if any is unmet)

1. A live `simo0` instance with its schema applied (`ema create
   srv/simo0-D03J4K6RM0K7X8E4`).
2. A `replication` transport account on the primary, granted
   `REPLICATION SLAVE`. It is created beforehand by the framework's
   `bin/replica-bootstrap` CLI on the primary and is deliberately **not** part
   of the shared `srv/roles-<GUID>` declaration nor the `gen-service-accounts`
   reconcile — the reconcile must never see or touch it (it is allow-listed).
3. A snapshot of `simo0` to restore on `simo1`, plus its replication
   coordinate (see below).

The replica build refuses to proceed if the snapshot is missing or unreadable,
its coordinate is absent, or the `replication` account is missing/wrong.

## Snapshot + coordinate

The framework's `bin/replica-bootstrap` takes a consistent `mariabackup`
snapshot of `simo0` (prepared before shipping) and records its replication
coordinate — `mariabackup` is the only snapshot format `ema create
--from-snapshot` accepts.

- Concrete coordinate today: **binlog file/position** (from
  `xtrabackup_binlog_info`), which the replica build resumes from.
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
framework's `bin/replica-bootstrap` (see
`php_daas_framework/doc/system/replica-bootstrap.md`). The replica build
itself is `ema create srv/simo1-<GUID> --from-snapshot <path>`, run on the
replica host (`EMA_TARGET=prod`).
