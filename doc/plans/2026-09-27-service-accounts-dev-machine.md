# Reconcile service accounts from the dev machine — Plan & Progress

Date: 2026-09-27
Repos: simox (this repo); php_daas_framework (upstream framework).

## Decision

simox's service-account reconcile runs from the **dev machine**, not from the DB
host. The reconcile resolves its host set from the private `etc/team.ini` (the
`member` source) and `etc/machines.ini` (the `worker`/`web` tag sources), and both
are dev-only: `DEPLOY_PRIVATE_FILES` ships only `reuter.ini`. Run on the DB host,
the resolver finds neither file, so no source maps to any host — the reconcile
emits no account and exits 0: a silent no-op that reports success.

The framework's `gen-service-accounts` bridges the two sides itself: it plans
where the roster lives and applies over `ssh root@<host>` on the database's host,
running the deployed repo's own `ema` there, against the instance's own socket —
the same run the CLI used to perform from the DB host. The target is the host
carrying the database's `db:<name>` roster token (the address the deploy chain
already uses for SSH), so no alias-to-IP conversion is involved, and the
reconcile needs no database account of its own: no `DBUSER` to export, no
privileged account on the TCP port, no `$allowlist` entry to remember.

No extra private file ships to prod: the "only the connectivity file reaches a
host" invariant in `doc/system/private-config.md` holds, and the roster stops
being runtime authority on a host — a stale `team.ini` there would otherwise
silently revoke a member's access on the next run.

Out of scope: the declaration (`srv/roles-<GUID>`, `srv/<db>.roles-<GUID>`), the
account model, the role/source routing table, and `reuter.ini` content.

See `php_daas_framework/doc/plans/2026-09-28-service-accounts-ssh-transport.md`
for the framework-side mechanism. It supersedes that repo's
`2026-09-27-service-accounts-remote-reconcile.md`, whose ema-side TCP transport
(ema's `doc/plans/2026-09-27-mariadb-remote-transport.md`) the reconcile does not
use: ema's `mariadb` verb keeps both of its transports, this flow just does not
need the off-host one.

## Changes

### simox (this repo)

- [x] `doc/system/service-accounts.md` — Quick setup: run
      `gen-service-accounts <name>` from the dev machine (drop "on the DB host");
      state that the SQL is applied over ssh as `root` on the host carrying the
      `db:<name>` tag, and that the reconcile needs no account of its own.
- [x] `doc/system/add-database.md` — Quick setup line and §6: run the reconcile
      from the dev machine, not the DB host.
- [x] `doc/system/private-config.md` — record the consequence of the roster being
      dev-only: the reconcile runs dev-side, which is why `machines.ini` and
      `team.ini` need no shipping.
- [x] `README.md` — "Service Accounts & Read Replica": the reconcile runs from
      the dev machine.
- [x] `composer.json` + `composer.lock` — bump `judijasa/php-daas-framework` to
      the commit carrying the change (Composer resolves the pin from the remote,
      so that commit must be pushed first); `judijasa/ema` follows its latest
      pushed commit. Re-pinned to `2e8f4a0` after the first pin (`c0cf62b`)
      failed on the host: a host has no `php` on `PATH`, so its `ema` could not
      run; `2e8f4a0` puts the nix result bin on that `PATH`.

## Open items

- **First real apply still pending** — the declaration has only ever been verified
  by dry-run (see `doc/plans/2026-09-18-per-database-db-roles.md` and
  `doc/plans/2026-09-11-role-based-host-pins.md`); the first apply on `simo0` and
  `simo1` follows this change.
- **`simo1` has no `simo1` database yet** — the instance behind its socket
  (`/var/lib/mariadb/simo1/mysql.sock`, the `[simo1]` section) holds the
  snapshot-copied `simo0` and no `simo1`, so `ema mariadb simo1` there answers
  `ERROR 1049 (42000): Unknown database 'simo1'`. Live state cannot be read on
  that host, so an apply there aborts; `simo0` is unaffected. The step that
  creates it (`ema create srv/simo1-D0L4SEWTLXQQSVJC --from-snapshot …`, see
  `doc/system/replica-bootstrap.md`) has not completed.
- **`web` host on `simo0`** — unchanged: `simox_web` holds no grant on `simo0`, so
  the reconcile drops `simox@<web-ip>` there while creating it on `simo1`.
- **Replica-side stale grants** — the open item in
  `doc/plans/2026-09-18-per-database-db-roles.md` (snapshot-copied `simo0` grant
  rows arriving on `simo1`) is unaffected and still to be checked after the first
  real apply.
- **The host must have been deployed at least once** — the reconcile runs the
  host's `vendor/bin/ema`, which `composer install` delivers at deploy time. The
  dev machine's own framework version is what the pin controls; the host's `ema`
  only has to be able to run `ema mariadb <name>` locally, which any deployed
  copy can.
