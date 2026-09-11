# simox service-account + read-only replica — Plan & Progress

Date: 2026-09-10
Repos: simox (this repo); ../ema (upstream, replica provisioning);
       ../php_daas_framework (replica bootstrap helper). Private config-repo
       data (reuter.ini/machines.ini/team.ini) is tracked in the private
       config repo's own plan doc.

## Decision

Collapse the three service accounts (`admin`/`reader`/`public`) into two — one
writer `simox` and one read `public` — and add a read-only replica so the
public website reads a copy, never the writable primary. The primary database
is `simo0` (writable); the replica is `simo1` (`read_only=1`, serves the
website). `simox` is host-pinned to team members ∪ worker hosts; `public` is
host-pinned to web hosts. Both stay **passwordless**: the
security boundary is ZeroTier membership + the source-IP host pin, and
revocation is dropping the `simox@<ip>` account (in addition to removing the
node from ZeroTier).

`bin/gen-service-users` becomes a **reconcile** (create + drop stale) that
reads a committed service-account SQL file and derives the host-pin set from
`etc/team.ini` (member IPs) and `etc/machines.ini` (`worker`/`web` tags),
creating `simox` on both `simo0` and `simo1` but `public` only on `simo1`
(never on `simo0`). Web-only and db-only hosts get no writer account: they run
no DB-touching script.

The `replication` transport account lives **on the primary** (global
`REPLICATION SLAVE`, host-pinned to the replica host, passwordless). It is
created **beforehand by the bootstrap** (a php_daas_framework helper run from a
dev machine with root SSH to the primary) — not by ema and not by the
reconcile. ema's replica build is local-only and **fails loudly** if the
bootstrap preconditions (snapshot + `replication` account) are missing.

This supersedes the three-account, single-database model in
`doc/plans/2026-09-07-ema-schema-only-and-service-users.md` and adjusts
`doc/plans/2026-09-09-ema-instance-per-db-manual-reuter.md` (the manual
reuter.ini + ema-instance-at-create mechanism is unchanged; this plan only
changes the account policy and adds the replica).

## Discarded: Plan A (three accounts, no replica)

Plan A kept `admin`/`reader`/`public` on a single primary database,
host-pinning `admin`/`reader` to members∪workers and leaving `public` at `%`,
with no replica. Discarded in favor of Plan B's single writer + replica, which
drops the unused `reader` and isolates public reads from writes. Recorded so
it can be reverted to if the replica proves too costly to operate: the reverse
diff is renaming `simox` back to `admin` (+ re-adding `reader`) and dropping
the replica section/`db:` tag.

## Config model

| Account | Privileges | Host pin | Used by |
|---|---|---|---|
| `simox` | ALL on `simo0`, SELECT on `simo1` | team.ini member IPs ∪ machines.ini `worker` hosts | cron/indexer, member ad-hoc scripts |
| `public` | SELECT on `simo1` | machines.ini `web` hosts | website (`public/`) |

- `public` exists **only** on `simo1` (the replica): the reconcile never
  creates it on `simo0`.
- The `replication` transport account is created **on the primary** by the
  bootstrap helper (see Replica mechanism). It is global/instance-level
  (`REPLICATION SLAVE` on `*.*`), not a per-database grant, so it is neither
  part of `etc/service-users.sql` nor the reconcile.
- `reuter.ini` carries two sections — `[simo0]` (primary) and `[simo1]`
  (replica); the section header is the database name `Database::connectAs`
  resolves.
- Routing: `public/index.php`/`insight.php` → `connectAs('simo1','public')`;
  the indexer/agents → `connectAs('simo0','simox')`.
- Naming: primary = `0` suffix, replica = `1` suffix.
- `machines.ini` gains `db:simo0` and `db:simo1` tags (primary and replica may
  live on the same or different hosts); `worker` feeds the `simox` pin set,
  `web` feeds the `public` pin set.

## Replica mechanism (schematic-inspired)

`../schematic` models a replica as a **first-class server type** — distinct
from a primary — whose build effect is a dedicated `build_replica` (provision
the instance, initialize replication from the primary, no schema apply), while
every schema builder is read-only-aware and skips its DDL when it detects the
replica (the replica's schema comes from replication, never the builder). The
stable `name-<GUID>` identity names the replication slot.

Translated to MariaDB, the mechanism splits into three parts so the replica
host never holds root on the primary:

1. **Bootstrap (php_daas_framework helper, run from a dev machine with root
   SSH to prod).** One-time, before `ema create`:
   - on the primary: `CREATE USER 'replication'@'<replica-ip>'` (passwordless)
     + `GRANT REPLICATION SLAVE ON *.*`;
   - take a consistent snapshot of the primary (`mariabackup`, or `mysqldump
     --master-data`) and ship it to the replica host.
2. **Provision + attach (ema, local on the replica host).** `srv/simo1-<GUID>`
   declares `type='replica'` + `replica_of='simo0'` (+ `dbname => 'simo1'`).
   `ema create srv/simo1-<GUID>` **requires the shipped snapshot as an explicit
   input** (`--from-snapshot <path>`). It provisions the instance exactly like
   a primary — datadir, socket, systemd unit, my.cnf — but writes `read_only=1`
   + `replicate-rewrite-db = simo0 -> simo1` and **skips schema apply**. It
   then restores the snapshot, runs `CHANGE MASTER TO ...
   MASTER_USER='replication'`, and `START SLAVE`. ema reaches the primary only
   over the low-priv `replication` account, never as root.
3. **Fail loudly (ema).** Three gates guard the replica build; ema aborts
   loudly on any failure:
   - **snapshot absent** → `--from-snapshot` missing → fail before provisioning
     anything;
   - **snapshot from the wrong database/server** → the snapshot records its
     source `server_uuid` + binlog/GTID coordinate (`xtrabackup_binlog_info` /
     `--master-data` header); ema checks it against `replica_of` (`simo0`) at
     restore and fails on mismatch — and with GTID, a foreign snapshot's GTID
     domain also fails `START SLAVE` (cannot reconcile with the real primary);
   - **`replication` account missing/mis-pinned** → after `START SLAVE`, ema
     checks `SHOW SLAVE STATUS`: the IO thread cannot authenticate and
     `Slave_IO_Running` is not `Yes`, so ema aborts telling the operator to run
     the bootstrap.

Differences from Postgres: MariaDB replication is binlog/GTID-based (no logical
slots); the initial copy is `mariabackup`/`mysqldump`, not `pg_basebackup`. The
transport is a low-privilege `replication` account on the primary rather than a
superuser slot. Co-locating the first replica (primary + replica on one server)
lets the bootstrap run over the primary's local socket and defers the
split-host question until it is actually needed.

## Changes

### simox (this repo)

- [x] `srv/simo-D03J4K6RM0K7X8E4` → `srv/simo0-D03J4K6RM0K7X8E4` (rename dir
      + `dbname` → `simo0` in `default.php`).
- [x] `srv/simo1-<GUID>` (new) — replica package: `type='replica'`,
      `replica_of='simo0'`, `dbname => 'simo1'` (no `$dependencies`; schema
      arrives via replication).
- [x] `etc/service-users.sql` (new) — committed service-account DDL
      (`simox`/`public` CREATE USER + GRANT, `{{dbname}}` placeholder); the
      single source the reconcile reads. `public` is tagged simo1-only so the
      reconcile skips it on `simo0`. (The `replication` account is
      bootstrap-created and not part of this file.)
- [x] `bin/gen-service-users` — reconcile (create + drop stale): read
      `etc/service-users.sql`, derive the `simox` host set from `etc/team.ini`
      + `etc/machines.ini` `worker` tag and the `public` host set from the
      `web` tag; apply to `simo0` and `simo1`, creating `simox` on both but
      `public` only on `simo1`. Drop the hardcoded admin/reader/public map and
      the single `SERVERNAME`.
- [x] `pkg/*/upgrade.sql` (21 files) — `public` grants `'public'@'%'` →
      reconcile-owned host-pinned grant (per-table vs db-level SELECT is an
      Open item).
- [x] `public/index.php`, `public/insight.php` — `$dbname` → `'simo1'`
      (account stays `public`).
- [x] `src/scripts/indexer/get_new_jobs.php` — `$dbname` → `'simo0'`, account
      → `'simox'`.
- [x] `src/scripts/indexer/get_jobs.php`, `src/scripts/pipeline/pipeline.php`
      — `#[Agent(dbTarget: 'simo0', dbAccount: 'simox')]`.
- [x] `etc/reuter.ini.template` — `[simo0]`/`[simo1]` sections;
      `SIMOX_PASSWORD=`/`PUBLIC_PASSWORD=` (empty); drop `SERVERNAME`.
- [x] `etc/machines.ini.template` — `db:simo0`/`db:simo1` + `web`/`worker`
      examples; document that `worker` feeds the `simox` pin and `web` feeds
      the `public` pin.
- [x] `etc/team.ini.template` — member IPs now feed the `simox` pin set; the
      `subject`/client-cert wording is demoted (unused by this plan).
- [x] `etc/deploy.conf` — comment: accounts are `simox`/`public` (reconciled),
      replica section.
- [x] `doc/system/replica-bootstrap.md` (new) — operator procedure: run the
      php_daas_framework bootstrap against `simo0`, then `ema create`
      `simo1`-package with `--from-snapshot`; the bootstrap creates the
      `replication` account + snapshot, ema restores + attaches + verifies.
- [x] `README.md` — account model, replica routing, reconcile + revocation,
      pointer to `doc/system/replica-bootstrap.md`.
- [x] `doc/plans/2026-09-10-simox-service-account-and-read-replica.md` — this
      doc.

### php_daas_framework (../php_daas_framework)

Tracked in php_daas_framework's own plan when landed; the expected shape:

- [x] add a **generic `replica-bootstrap` helper** (in `bin/`, alongside
      `pf-deploy.sh`/`pf-provision.sh`) that, given a primary db section and a
      replica host, creates the `replication` account on the primary and ships
      a snapshot to the replica host. Generic over any consumer, not
      simox-specific.
- [x] (verify `db-check` still works with two `db:` names — no change
      expected.)

### ema (../ema)

Tracked in ema's own plan when landed; the expected shape:

- [x] `ema` — add a `replica` package type (`$db['type']='replica'` +
      `$db['replica_of']=<primary>`) and a **local-only** replica build path in
      `_cmd_create`: require the shipped snapshot as an explicit input
      (`--from-snapshot <path>`), provision the instance, write `read_only=1` +
      `replicate-rewrite-db=<primary>-><replica>`, skip schema apply, restore
      the snapshot, `CHANGE MASTER` + `START SLAVE`.
- [x] `ema` — fail loudly on the replica path: `--from-snapshot` absent →
      abort before provisioning; snapshot source (`server_uuid`/GTID) not
      `simo0` → abort at restore; after `START SLAVE`, verify
      `Slave_IO_Running` and abort if the `replication` account is
      missing/mis-pinned.
- [x] `ema` — `create`/`values` emit the usual `[<dbname>]` section; the
      replica emits `[simo1]`.
- [x] `ema` — document the **manual bootstrap** (in ema's own docs): with no
      php_daas_framework script doing it automatically, the operator must run
      every bootstrap step by hand before `ema create` — on the primary,
      `CREATE USER 'replication'@'<replica-ip>'` + `GRANT REPLICATION SLAVE
      ON *.*` (passwordless, host-pinned to the replica host), take a
      consistent snapshot (`mariabackup` or `mysqldump --master-data`), and
      ship it to the replica host.

### private config repo

reuter.ini two sections, machines.ini `db:simo0`/`db:simo1` + `web`/`worker`
roster, and team.ini member IPs are tracked in the private config repo's own
dated plan doc.

## Open items

- **Replication init specifics** — binlog position vs GTID, and the initial
  snapshot tool (`mariabackup` vs `mysqldump --master-data`) are undecided;
  GTID is preferred (stable identity, no log-file/pos bookkeeping, and its
  baked-in `server_id` detects a wrong-source snapshot at `START SLAVE`).
- **`public` grant granularity** — keep the per-table SELECT grants (in
  `pkg/*`, needs a host placeholder ema does not fill) vs. move to a
  reconcile-owned `GRANT SELECT ON {{dbname}}.*`. **Recommend db-level
  SELECT** (simpler; `public` is already host-pinned to trusted web hosts).
- **`simox` replica privilege** — SELECT on `simo1` (read) is assumed; the
  replica's `read_only=1` is the real write-block regardless of grant.
- **First `ema create`** — no prod instance exists yet; the `[simo0]`/`[simo1]`
  values and the first pin sets are recorded on the first real create.
- **Revocation is two-step** — dropping `simox@<ip>` (reconcile) and removing
  the node from ZeroTier are both required; the reconcile emits the DROP, the
  operator removes the node.
