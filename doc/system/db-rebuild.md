# Rebuilding the simo database

`simo0` (the writable primary) serves the `simo` schema; `simo1` is its
read-only replica and follows through replication, so only `simo0` is rebuilt.
The schema and the data are two independent parts: the schema is rebuilt from
the packages in this repo (step 1), the data is refilled by the crawlers
(step 2). Neither part touches the instance itself (datadir, systemd
unit, port, socket), and neither touches users/grants — those are reconciled by
the framework's `gen-service-accounts`.

Run everything on the database host, as `root`, from the repo root, inside a
`tmux-remote <host> <session>` session. No `EMA_TARGET` prefix and no exports:
`phprun` loads the host's generated `.env` itself (`EMA_TARGET=prod`,
`REUTER_INI`), and `ema` resolves `etc/reuter.ini` from the repo root.

## 1. Rebuild the schema

Both options end in the same state: an empty `simo` carrying the packages'
target schema (tables, views, `cursorseq`) and no users/grants. `ema create` is
create-only — it refuses while `simo` exists — and reuses an existing instance,
initializing a datadir only when the instance has no conf file.

### Option A — new instance

```bash
ema create srv/simo0-<GUID>
```

Initializes the datadir, picks a port, writes the instance conf, asserts and
starts `mariadb@<instance>`, creates the database and prints the `[simo0]`
section. Record that section in the private `etc/reuter.ini`; the host must have
been deployed once, since `ema create` asserts the `mariadb@.service` unit the
deploy installs (see [add-database.md](add-database.md)).

### Option B — reuse the existing instance

```bash
ema mdb simo0 -e 'DROP DATABASE simo'
ema create srv/simo0-<GUID>
```

The conf file exists, so the recorded port/socket are reused and only the
database is recreated — the replica keeps replicating from the same endpoint.
The drop is typed first because ema has no delete verb and `ema create` refuses
while the database exists (see [add-database.md](add-database.md)). Between the
two commands the section's `DBNAME` no longer resolves, so `ema mdb simo0` fails
until the second command runs; anything needed in that window goes through the
mariadb client on the instance socket (`MYSQL_UNIX_PORT` in `[simo0]`).

> `ema create -n srv/simo0-<GUID>` prints the equivalent SQL instead of running
> it. Do not apply that stream by hand: it begins with the srv bootstrap's
> `DROP DATABASE IF EXISTS`, so it destroys the database with no existence check
> in front of it, and the package DDL is unqualified — a standalone file needs
> `USE simo;` injected between the bootstrap and the first package statement.

## 2. Populate

The indexer is cron-owned (`0 2 * * *`): it fills `empleo_snapshot` with the
live offers. The pipeline is also cron-owned (`0 5 * * *`) but is run here by
hand because the next step depends on its output.

| # | Command | Effect |
|---|---|---|
| 1 | `phprun 'src/scripts/simo/historico/indexer/main.php:backfill()'` | historical archive → `empleo_snapshot`, `source='historico'`; re-run until a pass from page 0 reports 0 new rows |
| 2 | `phprun 'src/scripts/simo/publico/pipeline/main.php:main()'` | `empleo_snapshot` → `empleo`, the dimension tables and `convocatoria` |
| 3 | `phprun 'src/scripts/simo/convocatoria/main.php:backfill()'` | classifies `convocatoria` and denormalizes `opec_historico` onto `empleo` |
| 4 | `phprun 'src/scripts/cnsc/indexer/main.php:backfill()'` | avisos → `cnsc_aviso_snapshot`; independent |

`historico:backfill()` is the long pole (~66k offers). Skipping `cnsc` leaves
the CNSC archive missing: the 18:00 job only adds avisos newer than what is
already stored.

## 3. Verify

```bash
ema mdb simo0 -e "SELECT count(*) FROM empleo_snapshot;
                  SELECT count(*) FROM empleo;
                  SELECT count(*) FROM cnsc_aviso_snapshot;"
```

## Pipeline cursor

`pipeline:main()` scans `empleo_snapshot` through `BatchScan`, which resumes from
the `pipeline_main` row in `cursorseq`. Rebuilding the schema resets it — the
table is recreated empty — so the first pass scans from `min(id)`. Emptying the
data tables instead of dropping the database leaves that row behind: the run
then logs `nothing to process (cursor past max_id)` and does nothing. Delete the
row to rescan.

## Steady state is cron-owned

Do not run these by hand — the deploy-installed manifest does — except the
pipeline during a rebuild (step 2). Disable the cron agents for the duration of
a rebuild: they write the same tables, so a rebuild running alongside them
conflicts.

| Job | Schedule | Scope |
|---|---|---|
| `publico/indexer:main()` | `0 2 * * *` | `worker` |
| `convocatoria:update()` | `0 4 * * *` | `worker` |
| `publico/pipeline:main()` | `0 5 * * *` | `worker` |
| `cnsc/indexer:update()` | `0 18 * * *` | `worker` |
| `maintenance:trim_log_files()` | `0 6 * * 6` | `host` |
| `maintenance:nix_store_gc()` | `0 8 * * 6` | `host` |
