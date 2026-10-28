# Sequence ids + guarded inserts, with a full rebuild

Date: 2026-10-27
Status: draft, refining

## Symptom

`pipeline main()` dies mid-batch on the prod primary:

```
SQLSTATE[22003]: Numeric value out of range: 167 Out of range value for column 'id' at row 1
BatchInsert into table 'tipo_entidad' (columns: code, nombre) failed
```

`tipo_entidad.id` is `tinyint(3) unsigned AUTO_INCREMENT`, 15 rows, `MAX(id)=148`,
`AUTO_INCREMENT=256`. The counter is exhausted.

## Root cause

InnoDB reserves an `AUTO_INCREMENT` value for every row in the VALUES list *before*
resolving unique keys, so a row rejected as a duplicate still spends its value. The
pipeline re-offers every existing lookup row on every batch, so the counter counts
attempts, not rows. The counter is monotonic and never returns discarded values.

## Direction

Replace `AUTO_INCREMENT` with native MariaDB `SEQUENCE` objects for tables populated by
code, and consume a sequence value only for rows actually inserted. Then rebuild the
database from scratch — drop every table including `empleo_snapshot`, re-crawl the
historico archive, and replay the pipeline and convocatoria passes — so every id is
tight (1..N) from the start.

## Settled

- **D1 — The defect is the write pattern.** Stop offering rows that already exist.
- **D2 — Keep `BatchInsert`; add a guarded primitive** in `php_daas_framework`
  (provisional name `GuardedInsert`), requiring explicit unique-key columns checked
  against the schema. No backward-compatibility break to `BatchInsert`.
- **D-seq — Native `SEQUENCE` objects**, consumed explicitly in the guarded insert, never
  via a column default (a default evaluates per offered row and burns like
  `AUTO_INCREMENT`). `id` carries no default so a naive writer fails instead of burning.
- **D-guard — The Guard is used, not an alternative.** With sequences, the guard is what
  stops the sequence burning: it filters duplicates so `NEXT VALUE FOR seq` is only
  evaluated for new rows. Sequence + guard compose; neither substitutes for the other.
- **Why both — the sequence's distinctive advantage.** The guard stops the waste; the
  sequence makes the counter *maintainable*: it can be reset to `MAX(id)+1`, shared
  across tables, pre-allocated in ranges, or set to reuse gaps — none of which
  `AUTO_INCREMENT` permits. Allocation becomes a first-class, explicit object instead of
  a side effect of the insert. (Also to live in the `GuardedInsert` class docblock.)
- **D5 — The duplicate path stays non-fatal** as the concurrency race guard.
- **D3 — No renumbering/compaction.** Unnecessary now: the rebuild produces tight ids.
- **D-rebuild — Full rebuild**, reusing the existing instance: drop all tables including
  `empleo_snapshot`, re-crawl the historico archive, replay the pipeline and
  convocatoria passes. Tight ids everywhere is an explicit goal.

## Sequence mechanics (O4) — verified on MariaDB 11.8

- `CREATE SEQUENCE` works with the `simox` account; `NEXT VALUE FOR seq` and
  `NEXTVAL(seq)` both parse; `NEXT VALUE FOR` inside `INSERT … SELECT … WHERE NOT EXISTS`
  yields tight ids (1,2,3,4 after five re-offer rounds — no burn); a no-default `id`
  makes a naive insert fail with error 1364.

## Schema design (O6) — settled

- **Tables:** all 15 surrogate-id tables get a sequence, named `<table>_id_seq`; the 5
  id-less tables (`empleo_vacante`, `empleo_requisito`, `empleo_funcion`, `cursorseq`,
  `ema_revision`) are untouched.
- **id type:** uniform `INT` (signed), sequences declared `AS INT`, and every FK column
  referencing an id becomes `INT`. `empleo_snapshot.id` stays `INT` (volume does not
  justify BIGINT).
- **`id` carries no default** — the guarded insert supplies it; naive writers fail
  (error 1364, verified).
- **Sequence `CACHE 50`** — bounds a restart gap to ≤50 in exchange for ~50× fewer
  per-value updates. (Recommended; awaiting explicit sign-off.)
- **Declaration:** `CREATE OR REPLACE SEQUENCE` (verified) inside each `pkg/*/upgrade.sql`
  beside its table.

## Open

- **O5 — settled: `GuardedInsert::insert()`.** Mirrors `BatchInsert::insert()`.
- **O7 — settled: bypass `rev/` on rebuild; delete after all instances rebuilt.** A
  from-scratch build re-runs `pkg/*/upgrade.sql` (`CREATE OR REPLACE`), producing the
  final schema directly — the `rev/` ALTERs only migrate existing data and never apply
  to a rebuild. Revisions are needed only until every instance on the old schema has
  been migrated or rebuilt; once `simo0` and `simo1` are rebuilt, the three `rev/` dirs
  are deletable (`git rm`, recoverable from history).

## Implementation work items

1. Rewrite `pkg/*/upgrade.sql` for the 15 tables: `CREATE SEQUENCE <table>_id_seq AS INT
   CACHE 50`, `id INT` with no default (drop `AUTO_INCREMENT`), FK columns → `INT`.
2. Implement `Utils\DatabaseOps\GuardedInsert::insert()` in `php_daas_framework`
   (per its own plan) and re-pin composer.
3. Convert the write call sites to `GuardedInsert` with `NEXT VALUE FOR <table>_id_seq`.
4. Gate: `php -l` + `phpstan`; deploy code.

## Operative rebuild procedure

Precondition: the schema changes and the guarded write path are implemented, gated,
and deployed. Order is strict: pause writers → reset primary → verify replica →
re-crawl → verify → resume writers.

### 1. Pause the DB writers

Comment out the `#[CronJob]` attribute (keep `#[Agent]`) on every agent that writes
`simo0`, then `deploy <worker-host>` so the cron step drops those jobs:

- `src/scripts/simo/publico/indexer/main.php` — `0 2 * * *`, scope `worker`
- `src/scripts/simo/convocatoria/main.php` — `0 4 * * *`, scope `worker`
- `src/scripts/simo/publico/pipeline/main.php` — `0 5 * * *`, scope `worker`
- `src/scripts/cnsc/indexer/main.php` — `0 18 * * *`, scope `worker`

The one-time `backfill()` agents carry no `#[CronJob]` and need nothing. Alternative
to the code edit: stop the orchestrator cron on the worker host for the window.

### 2. Reset the primary schema — re-use the instance, do NOT recreate it

Recreating the instance is the wrong move: `ema create` re-provisions the datadir,
picks a new TCP `PORT`, and rebuilds `mysql.*` — losing the SSL/binlog `my.cnf`, the
service accounts/grants, and the replication coordinates. Reuse the running
`mariadb@simo0` instance and reset only the `simo` database.

On the primary DB host, inside a `tmux-remote <primary> <session>` session:

```bash
ema create --dry-run srv/simo0-<GUID> > /tmp/simo-schema.sql   # capture the full bootstrap SQL
ema mdb simo0 <<'SQL'
DROP DATABASE simo;
SQL
ema mdb simo0 < /tmp/simo-schema.sql                           # re-apply: CREATE DATABASE + CREATE OR REPLACE + CREATE SEQUENCE
```

The instance, `my.cnf`, `PORT`, accounts and grants are untouched. Confirm the schema
is empty and pristine: sequences exist and are at `1`, `id` columns are `INT` with no
default.

### 3. Verify the replica follows

The replica replays the primary's binlog, so its `simo` schema resets by replication —
no separate build. Confirm replication is caught up (`Seconds_Behind_Master = 0`, no
IO/SQL thread error) before re-crawling.

### 4. Re-crawl (one-time passes, under tmux, in order)

1. historico backfill → fills `empleo_snapshot`
2. pipeline `main()` → fills the derived tables (the pass that previously died on
   `tipo_entidad`)
3. convocatoria backfill → fills `empleo.opec_historico`

### 5. Verify

- Tight ids everywhere: `MAX(id) = COUNT(*)` per table, no gaps.
- Sequences advanced only by real inserts (spot-check positions).
- Replica lag `0`; the site reads cleanly from the replica.

Note: the site reads the replica, so it is empty from step 2 until step 4 repopulates
it — the re-crawl duration is effectively site downtime.

### 6. Resume writers and clean up

Un-comment the `#[CronJob]` attributes and `deploy <worker-host>` again. After both
instances are rebuilt, delete the three `rev/` directories (`git rm`, recoverable from
history).

## Out of scope

- The historico resume/retry work (committed in `0250690`, `7202c0d`).
- Column widening already applied (`convocatoria`, `denominacion`).
