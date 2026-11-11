# ClickHouse cron-job metrics ingestion — Plan & Progress

Date: 2026-11-09
Repos: simox (this repo, consumer); php_daas_framework (../php_daas_framework,
       upstream mechanism); simox_cnf (../simox_cnf, private endpoint config).
       The ClickHouse server (schema + endpoint) is operated separately.

## Decision

Ingest simox cron-job run metrics into the ClickHouse `analytics` database,
whose schema is provided by the ClickHouse server operator. Transport is
plaintext HTTP (port 8123) over ZeroTier; auth is a passwordless ClickHouse
service user, access gated by ZeroTier membership and a source-IP allowlist on
the CH server. Instrumentation is automatic through a framework hook seam, so
every `#[Agent]` run emits a `start`/`finish` pair with no per-script
boilerplate and no duplicated runner.

Rationale: ZeroTier already encrypts the wire and authenticates each peer, so
TLS/mTLS is redundant crypto for low-sensitivity telemetry. The win is
operational — no CA, no cert issuance/rotation, no `SSL_DIR` plumbing through
cron. Native TCP (9000/9440) still has no PHP driver, so HTTP 8123 is the
PHP-reachable interface. Client auth is peer identity + firewall allowlist
rather than app-layer mTLS. The hooks keep the framework ClickHouse-blind and
simox the sole owner of the recording logic.

Settled choices:

- **Transport** — HTTP 8123 (plaintext) over ZeroTier.
- **Auth** — passwordless service user `CLICKHOUSE_USER` (the ClickHouse
  analog of the MariaDB service accounts); `CLICKHOUSE_PASSWORD` optional env
  override, empty by default. No `SSL_DIR`, no cert.
- **Config** — `etc/clickhouse.ini.template` (committed placeholders) +
  `etc/clickhouse.ini` (private, materialized from simox_cnf), mirroring
  `reuter.ini.template`/`reuter.ini`. Endpoint-only, no credentials in the file.
- **Integration** — framework `phprun()` hook seam: optional `function_exists`
  hooks `on_job_start`/`on_job_finish`; simox defines them in an autoloaded
  `src/utils/clickhouse.php`.

## Target schema (provided by the CH server operator, already created)

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

- [ ] `src/utils/clickhouse.php` — CH client (HTTP 8123 via curl,
      `JSONEachRow`, `async_insert=1`) plus `on_job_start`/`on_job_finish` and
      `ch_counter($metric, $value)`. `on_job_start` generates `run_id` and
      holds it (with the start timestamp) in module state; `on_job_finish` and
      `ch_counter` read that state.
- [ ] `composer.json` — `"autoload": {"files": ["src/utils/clickhouse.php"]}`
      so `phprun.php`'s `require 'vendor/autoload.php'` loads the hooks.
- [ ] `etc/clickhouse.ini.template` — committed placeholders:
      `[clickhouse:analytics]` `host = <CH ZeroTier IP>`, `port = 8123`.
- [ ] `bin/fetch-private-data` — materialize `clickhouse.ini` from the private
      source (warn-if-missing, like `reuter.ini`).
- [ ] `DEPLOY_PRIVATE_FILES` — add `clickhouse.ini` so it ships to prod.
- [ ] `etc/dev.default.conf` — add `CLICKHOUSE_USER` default (the passwordless
      service user; optional `CLICKHOUSE_PASSWORD`).
- [ ] Update `judijasa/php-daas-framework` (floating `dev-main`) after the
      framework change lands.
- [ ] `doc/plans/2026-11-09-clickhouse-metrics-ingestion.md` — this doc.

### simox_cnf (../simox_cnf — private)

- [ ] `clickhouse.ini` — real `[clickhouse:analytics]` endpoint (`host`/`port`),
      no credentials.

### ClickHouse server (operated externally)

- [ ] Expose the HTTP interface (8123) on ZeroTier, allowlist simox's ZeroTier
      IPs (firewall or ClickHouse `<networks>`), and provision the passwordless
      service user used by `CLICKHOUSE_USER`.

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
