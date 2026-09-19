# Per-database `db` roles — Plan & Progress

Date: 2026-09-18
Repos: simox (this repo); simox_cnf (../simox_cnf, private config — header
       comment only).

## Decision

Split the single `simox_db` role into one role per database —
`simox_db_simo0` and `simox_db_simo1` — each granted **only on its own
database**. `simox_db_simo1` holds no grant on `simo0`, so a host tagged
`db:simo1` can never write the primary.

The old mapping sent both `db:simo0` and `db:simo1` to the same `simox_db`
role, and that role carried `ALL PRIVILEGES` on `simo0`
(`srv/simo0.roles-D0TVLE3YJCFE1A8U`). Grants are role-level, not host-level:
`gen-service-accounts` grants each host the union of its tags' roles
intersected with the roles that hold a grant on the target database, so any
host reaching `simox_db` on `simo0` got the full write set. A host tagged
`db:simo1, web` — the layout the `etc/machines.ini` example recommends —
therefore held DDL+DML on the primary while being the box exposed to the
public website. `simox` is passwordless and pinned only by ZeroTier membership
plus source IP, so that pin gives no protection against a compromise of the
pinned host itself.

This restores the intent of
`doc/plans/2026-09-10-simox-service-account-and-read-replica.md` ("web-only and
db-only hosts get no writer account") while keeping `db:<name>` a real
role-pin source, as introduced in
`doc/plans/2026-09-11-role-based-host-pins.md`.

Out of scope: the `worker` and `web` grants (a `worker` host legitimately
writes `simo0`), the account model (still one shared passwordless `simox`), and
the framework mechanism (unchanged — the split is pure consumer data).

## Config model

| Source     | Role             | `simo0`        | `simo1` |
|------------|------------------|----------------|---------|
| `member`   | `simox_member`   | ALL PRIVILEGES | SELECT  |
| `worker`   | `simox_worker`   | ALL PRIVILEGES | SELECT  |
| `db:simo0` | `simox_db_simo0` | ALL PRIVILEGES | —       |
| `db:simo1` | `simox_db_simo1` | —              | SELECT  |
| `web`      | `simox_web`      | —              | SELECT  |

A role with no grant on a database means the account is not wanted there, so on
`simo0` a host tagged `db:simo1, web` holds no role at all and its `simox@<ip>`
is dropped by the reconcile's Phase 4.

The invariant this buys: privileges follow **what a host runs** (`worker`,
`web`), not **what it stores** (`db:<name>`). Corollary: the `worker` tag must
never be co-located with the web-facing host, or write access to the primary is
required there again, by design.

## Changes

### simox (this repo)

- [x] `srv/roles-D0YRR7WII6V1XDZR/default.php` — `$sources`: `db:simo0` →
      `simox_db_simo0`, `db:simo1` → `simox_db_simo1`; `$allowlist`:
      `array('replication')` (the closed-world drop must never remove it; the
      engine-internal `root`/`mariadb.sys` stay on the framework's implicit
      floor).
- [x] `srv/roles-D0YRR7WII6V1XDZR/upgrade.sql` — `CREATE ROLE` for both
      per-database roles; `simox_db` dropped.
- [x] `srv/simo0.roles-D0TVLE3YJCFE1A8U/upgrade.sql` — grants
      `simox_db_simo0`; `simox_db_simo1` absent.
- [x] `srv/simo1.roles-D0A3HGW7BHWSVCUQ/upgrade.sql` — grants
      `simox_db_simo1`; `simox_db_simo0` absent.
- [x] `etc/machines.ini.template` — header comment: `db:<name>` →
      `simox_db_<name>` (granted only on its own database).
- [x] `README.md` — Remote Access bullet + Service Accounts source → role
      table.
- [x] `doc/plans/2026-09-18-per-database-db-roles.md` — this doc.

### simox_cnf (../simox_cnf)

- [ ] `machines.ini` — header comment: `db:<name>` → `simox_db_<name>`
      (granted only on its own database). Its current text documents the
      removed shared role and the `ALL on simo0` it granted.

## Open items

- **Live apply** — the declaration is verified by a `gen-service-accounts -n`
  dry-run against a probe roster only; no prod instance exists yet, so the
  first real apply on `simo0`/`simo1` is still pending.
- **Snapshot-copied grants on the replica** — the replica instance is restored
  from a primary snapshot, so the primary's `mysql.*` grant rows (naming
  `simo0`) arrive with it, while the reconcile's revoke is scoped
  `ON <target db>.*`. Check `mysql.db` on the replica after the first real
  build; a surviving stale `simo0` grant there is a framework-side
  revoke-scope gap, not a declaration bug.
- **Framework allow-list awareness** — the always-on floor is now just
  `root`/`mariadb.sys` (`replication` was removed from `gen-service-accounts`),
  so a consumer must declare any account it creates itself; `replication` is
  declared here in `$allowlist`. Remaining upstream follow-up: the reconcile
  output never states the effective allow-list, so print it in the dry-run/SQL
  header for observability.
- **`simox_worker` on `simo1`** — kept: it is the union's accident, not a need
  (a cron host connects to `simo0`). Dropping it would remove a worker host
  from the replica instance entirely; no security gain today, since the
  `worker` host is trusted with the primary anyway.
- **No live migration** — with no prod instance, the removed `simox_db` role
  needs no cleanup. Where it did exist, the reconcile's orphaned-role pass
  (`DROP ROLE`) removes it on the next run, as long as the host still holding
  it is still in the roster (the pass reads live role memberships only for
  hosts the current roster resolves).
