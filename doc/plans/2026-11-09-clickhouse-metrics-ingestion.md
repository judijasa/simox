# ClickHouse cron-job metrics ingestion — Plan & Progress

Date: 2026-11-09
Repos: simox (this repo, consumer); php_daas_framework (../php_daas_framework,
       upstream mechanism); hlab (../hlab, schema owner); simox_cnf
       (../simox_cnf, private endpoint config).

## Decision

Ingest simox cron-job run metrics into the ClickHouse `analytics` database
whose schema is owned by ../hlab. Transport is HTTPS (port 8443) with mTLS
using the existing machine client cert; auth is passwordless. Instrumentation
is automatic through a framework hook seam, so every `#[Agent]` run emits a
`start`/`finish` pair with no per-script boilerplate and no duplicated runner.

Rationale: native TCP (9000/9440) has no PHP driver and only pays off for
high-throughput reads; OTLP imposes an SDK that does not map to the
hand-designed `simox_job_runs`/`simox_job_counters` schema; password auth
would ship a secret to prod. The hooks keep the framework ClickHouse-blind and
simox the sole owner of the recording logic.

Settled choices:

- **Transport** — HTTPS 8443 + mTLS.
- **Auth** — client cert from `SSL_DIR` (the same out-of-band machine cert as
  MariaDB); `CLICKHOUSE_USER` is a global default; `CLICKHOUSE_PASSWORD`
  stays empty (mTLS, no shipped secret).
- **Config** — `etc/clickhouse.ini.template` (committed placeholders) +
  `etc/clickhouse.ini` (private, materialized from simox_cnf), mirroring
  `reuter.ini.template`/`reuter.ini`. Endpoint-only, no credentials in the file.
- **Integration** — framework `phprun()` hook seam: optional `function_exists`
  hooks `on_job_start`/`on_job_finish`; simox defines them in an autoloaded
  `src/utils/clickhouse.php`.

## Target schema (owned by ../hlab, already created)

- `simox_job_runs` (MergeTree event-log) — `run_id`, `job`, `host`, `scope`,
  `event` (`start`|`finish`), `at`, `status`, `reason`, `duration_ms`. One
  `start` + one `finish` per run, correlated by `run_id`; a missing `finish`
  means crashed/hung. Finish status: `succeeded`/`failed`/`timeout`/
  `early_exit`.
- `simox_job_counters` (SummingMergeTree) — `run_id`, `job`, `host`, `metric`,
  `at`, `value`. One row per increment; `value` is the summed column.
- Database `analytics` (tag `clickhouse:analytics`).

## Changes

### php_daas_framework (../php_daas_framework)

- [ ] Extract the run into `phprun(string $arg): void` — parse selector,
      `require`, reflect `#[Agent]`, `Database::connectTo`, `eval`.
      `phprun.php` shrinks to `require 'vendor/autoload.php'; phprun($argv[1]);`.
- [ ] Read `#[CronJob]` scope at runtime (phprun today reflects only `#[Agent]`).
- [ ] Add three optional hook calls (each `function_exists`-gated,
      behavior-preserving):
      - `on_job_start($job, $scope)` before the run;
      - `on_job_finish('succeeded', $job)` after success;
      - `on_job_finish('failed', $job, $e->getMessage())` in `catch`, then rethrow.

### simox (this repo)

- [ ] `src/utils/clickhouse.php` — CH client (HTTPS + mTLS via curl,
      `JSONEachRow`, `async_insert=1`) plus `on_job_start`/`on_job_finish` and
      `ch_counter($metric, $value)`. `on_job_start` generates `run_id` and
      holds it (with the start timestamp) in module state; `on_job_finish` and
      `ch_counter` read that state.
- [ ] `composer.json` — `"autoload": {"files": ["src/utils/clickhouse.php"]}`
      so `phprun.php`'s `require 'vendor/autoload.php'` loads the hooks.
- [ ] `etc/clickhouse.ini.template` — committed placeholders:
      `[clickhouse:analytics]` `host = 10.99.x.x`, `port = 8443`.
- [ ] `bin/fetch-private-data` — materialize `clickhouse.ini` from the private
      source (warn-if-missing, like `reuter.ini`).
- [ ] `DEPLOY_PRIVATE_FILES` — add `clickhouse.ini` so it ships to prod.
- [ ] `etc/dev.default.conf` — add `CLICKHOUSE_USER` default.
- [ ] Re-pin `judijasa/php-daas-framework` after the framework change lands.
- [ ] `doc/plans/2026-11-09-clickhouse-metrics-ingestion.md` — this doc.

### simox_cnf (../simox_cnf — private)

- [ ] `clickhouse.ini` — real `[clickhouse:analytics]` endpoint (`host`/`port`),
      no credentials.

### hlab (../hlab)

- [ ] None — schema + `analytics` db already exist as doric packages; simox
      provides sources only.

## Open items (recommendations; confirm before implementation)

- **`host` identity** — recommend `gethostname()` with an optional `CH_HOST`
  env override.
- **Counter flush** — recommend incremental (each `ch_counter()` writes one
  row immediately); matches the schema's "one row per increment" and survives
  a crash, unlike accumulate-and-flush-at-finish.
- **Status coverage** — the hooks can emit only `succeeded`/`failed`. `timeout`
  (SIGKILL) and `early_exit` (mid-run `exit()`) leave no `finish` row, which
  the schema already reads as "crashed/hung"; producing those statuses
  explicitly is out of scope for v1.
- **Server cert trust** — the client verifies CH's server cert; use the
  project CA if CH is issued by it, otherwise a CH-specific CA path.
