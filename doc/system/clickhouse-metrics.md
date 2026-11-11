# ClickHouse cron-job metrics

Date: 2026-11-10
Scope: how simox reports `#[Agent]` run boundaries and counters to the shared
ClickHouse `analytics` database.

## Shape

`src/utils/clickhouse.php` implements the framework's optional instrumentation
hooks (the contract is in the framework's `doc/system/agents.md`), so every
`phprun` run emits a `start`/`finish` pair with no per-script boilerplate:

| Hook | Row |
|------|-----|
| `on_job_start($job, $scope)` | `simox_job_runs`, `event = 'start'` |
| `on_job_finish($status, $job, $reason)` | `simox_job_runs`, `event = 'finish'` |
| `ch_counter($metric, $value)` | `simox_job_counters`, one row per increment |

The file is loaded through Composer's `files` autoload, so anything that
requires `vendor/autoload.php` defines the hooks. A script calls
`ch_counter()` directly when it wants to report progress (records processed,
saved, ...); there is no other wiring.

`run_id` is generated at start and held in process state; the finish row and
the counter rows reuse it, which is how the event log correlates. Counters are
written incrementally, so a crashed run keeps the increments it already
reported. A `start` row with no matching `finish` means the run crashed, hung
or exited early; the `finish` statuses are `succeeded` and `failed`, and a
`failed` row carries the exception message.

The tables live in the `analytics` database and are owned by hlab's schema
packages (`clickhouse/pkg/simox_job_runs-*`, `clickhouse/pkg/simox_job_counters-*`);
simox only writes rows.

## Transport and auth

ClickHouse's HTTP interface over HTTPS (8443), one JSONEachRow line per insert,
with `async_insert=1`. Mutual TLS uses the same out-of-band machine client
certificate as MariaDB: `SSL_DIR/client.crt` + `client.key`, with the server
verified against `SSL_DIR/ca.crt` when that file is present. Auth is
passwordless — `CLICKHOUSE_USER` (default `default`) and no password — so no
secret ships to prod.

The endpoint is `etc/clickhouse.ini` (private data, from the committed
`etc/clickhouse.ini.template`):

```ini
[clickhouse:analytics]
host = 10.99.x.x
port = 8443
```

`CLICKHOUSE_INI` overrides the path and `CH_HOST` the reported host name
(default: `gethostname()`). `CLICKHOUSE_USER` comes from the environment — the
dev `.env` relays it from `etc/dev.default.conf`.

## Failure behavior

Instrumentation never fails a job: a missing config, an unreachable server or a
rejected insert is reported on STDERR (captured in the `phprun` log) and
swallowed. A missing `etc/clickhouse.ini` disables metrics for that process.

## Querying

```sql
-- finish events, last day
SELECT job, host, status, count() AS runs, avg(duration_ms) AS avg_ms
FROM analytics.simox_job_runs
WHERE event = 'finish' AND at > now() - INTERVAL 1 DAY
GROUP BY job, host, status ORDER BY runs DESC;

-- runs with no finish row (crashed or hung)
SELECT s.run_id, s.job, s.host, s.at
FROM analytics.simox_job_runs AS s
LEFT JOIN analytics.simox_job_runs AS f
  ON f.run_id = s.run_id AND f.event = 'finish'
WHERE s.event = 'start' AND f.run_id = '';

-- counters of one run
SELECT metric, sum(value) FROM analytics.simox_job_counters
WHERE run_id = '<run_id>' GROUP BY metric;
```

## Related

- [private-config.md](private-config.md) — `etc/clickhouse.ini` delivery.
- [machine-certs.md](machine-certs.md) — the client certificate mTLS uses.
