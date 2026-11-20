<?php

declare(strict_types=1);

// ClickHouse cron-job metrics — the consumer half of the framework's
// instrumentation seam (php_daas_framework's `phprun()` calls the two optional
// global hooks below through function_exists()).
//
// Composer's `files` autoload loads this file (see composer.json), so every
// process that requires vendor/autoload.php — notably `phprun` — defines the
// hooks. A process that does not (the web server) records nothing.
//
// Transport: ClickHouse's HTTP interface over plaintext HTTP (port 8123) on
// ZeroTier, whose wire encryption and peer identity make TLS/mTLS redundant.
// Auth is a passwordless service user (CLICKHOUSE_USER, default `default`)
// gated by ZeroTier membership and a source-IP allowlist on the server;
// CLICKHOUSE_PASSWORD is an optional override, empty by default. The endpoint
// and the database to write to live in etc/clickhouse-reuter.ini under
// [clickhouse:simo0]; that file carries no credential.
//
// Rows match the ClickHouse server's schema (simox_job_runs /
// simox_job_counters): one `start` + one `finish` row per run in
// <database>.simox_job_runs, correlated by run_id, plus one row per counter
// increment in <database>.simox_job_counters. A run with no `finish` row
// crashed, hung or exited early. Counters are written incrementally so a run
// that dies keeps the progress it reported.
//
// Metrics must never break the job they observe: every failure here is
// reported on STDERR (captured in the phprun log) and swallowed.

/**
 * Writes the run-boundary and counter rows described above.
 *
 * State is per-instance: start() opens a run, and finish() / counter() attach
 * to it. A counter outside a run has no run_id to correlate with, so it is
 * ignored. The global hooks share one default instance (the `clickhouse:simo0`
 * endpoint); a caller targeting a different endpoint instantiates its own.
 */
final class ClickHouseMetrics
{
    // Endpoint section in etc/clickhouse-reuter.ini. The header names the
    // endpoint; the database the tables live in is the section's `database`
    // value, not the header. It is a constructor argument (default
    // `clickhouse:simo0`) so a caller can point an instance elsewhere.
    private string $section;

    private ?string $runId = null;
    private string $job = '';
    private string $scope = '';
    private float $startedAt = 0.0;

    /** @var array{host: string, port: int, database: string, user: string, password: string}|null */
    private ?array $config = null;
    private bool $configLoaded = false;

    public function __construct(string $section = 'clickhouse:simo0')
    {
        $this->section = $section;
    }

    public function start(string $job, string $scope): void
    {
        try {
            $this->runId = bin2hex(random_bytes(16));
            $this->job = $job;
            $this->scope = $scope;
            $this->startedAt = microtime(true);

            $this->insert('simox_job_runs', [
                'run_id' => $this->runId,
                'job' => $job,
                'host' => $this->host(),
                'scope' => $scope,
                'event' => 'start',
                'at' => $this->now(),
            ]);
        } catch (Throwable $e) {
            $this->warn($e->getMessage());
        }
    }

    public function finish(string $status, string $job, string $reason = ''): void
    {
        try {
            if ($this->runId === null) {
                return;
            }

            $duration = (int) round((microtime(true) - $this->startedAt) * 1000);

            $this->insert('simox_job_runs', [
                'run_id' => $this->runId,
                'job' => $job,
                'host' => $this->host(),
                'scope' => $this->scope,
                'event' => 'finish',
                'at' => $this->now(),
                'status' => $status,
                'reason' => $reason,
                'duration_ms' => max(0, min($duration, 4294967295)),
            ]);

            $this->runId = null;
        } catch (Throwable $e) {
            $this->warn($e->getMessage());
        }
    }

    public function counter(string $metric, int $value = 1): void
    {
        try {
            if ($this->runId === null) {
                return;
            }

            $this->insert('simox_job_counters', [
                'run_id' => $this->runId,
                'job' => $this->job,
                'host' => $this->host(),
                'metric' => $metric,
                'at' => $this->now(),
                'value' => $value,
            ]);
        } catch (Throwable $e) {
            $this->warn($e->getMessage());
        }
    }

    /**
     * POST one JSONEachRow line to the HTTP interface. ClickHouse applies the
     * column DEFAULTs for fields omitted here, so the start row omits
     * status/reason/duration_ms. async_insert=1 batches concurrent writers
     * server-side; the client still waits for the flush before returning.
     *
     * @param array<string, string|int> $row
     */
    private function insert(string $table, array $row): void
    {
        $config = $this->config();
        if ($config === null) {
            return;
        }

        $json = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $url = 'http://' . $config['host'] . ':' . $config['port'] . '/?' . http_build_query([
            'query' => 'INSERT INTO ' . $config['database'] . '.' . $table . ' FORMAT JSONEachRow',
            'async_insert' => '1',
        ]);

        $handle = curl_init($url);
        if ($handle === false) {
            $this->warn('could not initialize curl');
            return;
        }

        $headers = ['X-ClickHouse-User: ' . $config['user']];
        if ($config['password'] !== '') {
            $headers[] = 'X-ClickHouse-Password: ' . $config['password'];
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers,
        ];
        curl_setopt_array($handle, $options);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($response === false) {
            $this->warn("$table insert failed: $error");
            return;
        }
        if ($status >= 400) {
            $this->warn("$table insert rejected (HTTP $status): " . trim(substr((string) $response, 0, 200)));
        }
    }

    /**
     * Endpoint, database and identity, read once per process. A missing file,
     * section or database disables metrics for this process rather than
     * failing the job.
     *
     * @return array{host: string, port: int, database: string, user: string, password: string}|null
     */
    private function config(): ?array
    {
        if ($this->configLoaded) {
            return $this->config;
        }
        $this->configLoaded = true;

        $override = getenv('CLICKHOUSE_INI');
        $path = is_string($override) && $override !== ''
            ? $override
            : dirname(__DIR__, 2) . '/etc/clickhouse-reuter.ini';

        $ini = is_file($path) ? parse_ini_file($path, true) : false;
        $section = is_array($ini) ? ($ini[$this->section] ?? null) : null;
        if (!is_array($section)) {
            $this->warn("no [" . $this->section . "] endpoint in $path; metrics are disabled");
            return null;
        }

        $rawHost = $section['host'] ?? null;
        $host = is_string($rawHost) ? trim($rawHost) : '';
        if ($host === '') {
            $this->warn("no host in [$path " . $this->section . ']; metrics are disabled');
            return null;
        }

        $rawPort = $section['port'] ?? null;
        $port = 8123;
        if (is_string($rawPort) && (int) $rawPort > 0) {
            $port = (int) $rawPort;
        }

        // The section header names the endpoint, not the database: the tables
        // live in the `database` value. It is interpolated into the INSERT, so
        // a plain identifier is required.
        $rawDatabase = $section['database'] ?? null;
        $database = is_string($rawDatabase) ? trim($rawDatabase) : '';
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $database) !== 1) {
            $this->warn("no valid database in [$path " . $this->section . ']; metrics are disabled');
            return null;
        }

        $user = getenv('CLICKHOUSE_USER');
        $password = getenv('CLICKHOUSE_PASSWORD');

        $this->config = [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'user' => is_string($user) && $user !== '' ? $user : 'default',
            'password' => is_string($password) ? $password : '',
        ];

        return $this->config;
    }

    // gethostname() names the machine the run happened on; CH_HOST overrides
    // it (e.g. a stable name when the OS hostname is opaque).
    private function host(): string
    {
        $override = getenv('CH_HOST');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $name = gethostname();

        return is_string($name) && $name !== '' ? $name : 'unknown';
    }

    // DateTime64(3, 'UTC'): the column carries UTC, so send UTC rather than the
    // host's local time.
    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
    }

    private function warn(string $message): void
    {
        fwrite(STDERR, 'clickhouse: ' . $message . PHP_EOL);
    }
}

// The framework's phprun() calls these hooks via function_exists(); they share
// one default ClickHouseMetrics instance (the `clickhouse:simo0` endpoint).
// A caller that needs a different endpoint instantiates ClickHouseMetrics
// directly instead of going through the hooks.
function clickhouse_metrics(): ClickHouseMetrics
{
    static $metrics = null;

    return $metrics ??= new ClickHouseMetrics();
}

function on_job_start(string $job, string $scope): void
{
    clickhouse_metrics()->start($job, $scope);
}

function on_job_finish(string $status, string $job, string $reason = ''): void
{
    clickhouse_metrics()->finish($status, $job, $reason);
}

function ch_counter(string $metric, int $value = 1): void
{
    clickhouse_metrics()->counter($metric, $value);
}
