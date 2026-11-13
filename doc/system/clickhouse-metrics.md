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

The tables live in the `analytics` database, whose schema is provided by the
ClickHouse server operator; simox only writes rows.

## Server boundary

The ClickHouse server is operated separately from this repo: simox owns only
the client (`src/utils/clickhouse.php`) and the endpoint it needs to send rows
(`etc/clickhouse.ini`). Server-side provisioning, the `analytics` schema, the
`default` user and the source-IP allowlist are the server operator's concern.
simox's `gen-firewall`, `machines.ini` and `host-hardening.php` are out of
scope here — simox hosts only need outbound (already `allow outgoing` by
default), and inbound 8123 is the server's own firewall, not simox's.

## Transport and auth

ClickHouse's HTTP interface over plaintext HTTP (8123) on ZeroTier, one
JSONEachRow line per insert, with `async_insert=1`. ZeroTier already encrypts
the wire and authenticates each peer, so TLS/mTLS is redundant. Auth is a
passwordless service user — `CLICKHOUSE_USER` (default `default`) gated by
ZeroTier membership and a source-IP allowlist on the server; `CLICKHOUSE_PASSWORD`
is an optional override, empty by default — so no secret ships to prod.

The endpoint is `etc/clickhouse.ini` (private data, from the committed
`etc/clickhouse.ini.template`):

```ini
[clickhouse:analytics]
host = <CH ZeroTier IP>
port = 8123
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
