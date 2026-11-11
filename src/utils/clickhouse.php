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
// lives in etc/clickhouse.ini under [clickhouse:analytics]; that file carries
// no credential.
//
// Rows match the ClickHouse server's schema (simox_job_runs /
// simox_job_counters): one `start` + one `finish` row per run in
// analytics.simox_job_runs, correlated by run_id, plus one row per counter
// increment in analytics.simox_job_counters. A run with no `finish` row
// crashed, hung or exited early. Counters are written incrementally so a run
// that dies keeps the progress it reported.
//
// Metrics must never break the job they observe: every failure here is
// reported on STDERR (captured in the phprun log) and swallowed.

/**
 * Writes the run-boundary and counter rows described above.
 *
 * State is module-level: on_job_start() opens a run, and on_job_finish() /
 * ch_counter() attach to it. A counter outside a run has no run_id to
 * correlate with, so it is ignored.
 */
final class ClickHouseMetrics
{
    private const SECTION = 'clickhouse:analytics';
    private const DATABASE = 'analytics';

    private static ?string $runId = null;
    private static string $job = '';
    private static string $scope = '';
    private static float $startedAt = 0.0;

    /** @var array{host: string, port: int, user: string, password: string}|null */
    private static ?array $config = null;
    private static bool $configLoaded = false;

    public static function start(string $job, string $scope): void
    {
        try {
            self::$runId = bin2hex(random_bytes(16));
            self::$job = $job;
            self::$scope = $scope;
            self::$startedAt = microtime(true);

            self::insert('simox_job_runs', [
                'run_id' => self::$runId,
                'job' => $job,
                'host' => self::host(),
                'scope' => $scope,
                'event' => 'start',
                'at' => self::now(),
            ]);
        } catch (Throwable $e) {
            self::warn($e->getMessage());
        }
    }

    public static function finish(string $status, string $job, string $reason = ''): void
    {
        try {
            if (self::$runId === null) {
                return;
            }

            $duration = (int) round((microtime(true) - self::$startedAt) * 1000);

            self::insert('simox_job_runs', [
                'run_id' => self::$runId,
                'job' => $job,
                'host' => self::host(),
                'scope' => self::$scope,
                'event' => 'finish',
                'at' => self::now(),
                'status' => $status,
                'reason' => $reason,
                'duration_ms' => max(0, min($duration, 4294967295)),
            ]);

            self::$runId = null;
        } catch (Throwable $e) {
            self::warn($e->getMessage());
        }
    }

    public static function counter(string $metric, int $value = 1): void
    {
        try {
            if (self::$runId === null) {
                return;
            }

            self::insert('simox_job_counters', [
                'run_id' => self::$runId,
                'job' => self::$job,
                'host' => self::host(),
                'metric' => $metric,
                'at' => self::now(),
                'value' => $value,
            ]);
        } catch (Throwable $e) {
            self::warn($e->getMessage());
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
    private static function insert(string $table, array $row): void
    {
        $config = self::config();
        if ($config === null) {
            return;
        }

        $json = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $url = 'http://' . $config['host'] . ':' . $config['port'] . '/?' . http_build_query([
            'query' => 'INSERT INTO ' . self::DATABASE . '.' . $table . ' FORMAT JSONEachRow',
            'async_insert' => '1',
        ]);

        $handle = curl_init($url);
        if ($handle === false) {
            self::warn('could not initialize curl');
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
            self::warn("$table insert failed: $error");
            return;
        }
        if ($status >= 400) {
            self::warn("$table insert rejected (HTTP $status): " . trim(substr((string) $response, 0, 200)));
        }
    }

    /**
     * Endpoint + identity, read once per process. A missing file or section
     * disables metrics for this process rather than failing the job.
     *
     * @return array{host: string, port: int, user: string, password: string}|null
     */
    private static function config(): ?array
    {
        if (self::$configLoaded) {
            return self::$config;
        }
        self::$configLoaded = true;

        $override = getenv('CLICKHOUSE_INI');
        $path = is_string($override) && $override !== ''
            ? $override
            : dirname(__DIR__, 2) . '/etc/clickhouse.ini';

        $ini = is_file($path) ? parse_ini_file($path, true) : false;
        $section = is_array($ini) ? ($ini[self::SECTION] ?? null) : null;
        if (!is_array($section)) {
            self::warn("no [" . self::SECTION . "] endpoint in $path; metrics are disabled");
            return null;
        }

        $rawHost = $section['host'] ?? null;
        $host = is_string($rawHost) ? trim($rawHost) : '';
        if ($host === '') {
            self::warn("no host in [$path " . self::SECTION . ']; metrics are disabled');
            return null;
        }

        $rawPort = $section['port'] ?? null;
        $port = 8123;
        if (is_string($rawPort) && (int) $rawPort > 0) {
            $port = (int) $rawPort;
        }

        $user = getenv('CLICKHOUSE_USER');
        $password = getenv('CLICKHOUSE_PASSWORD');

        self::$config = [
            'host' => $host,
            'port' => $port,
            'user' => is_string($user) && $user !== '' ? $user : 'default',
            'password' => is_string($password) ? $password : '',
        ];

        return self::$config;
    }

    // gethostname() names the machine the run happened on; CH_HOST overrides
    // it (e.g. a stable name when the OS hostname is opaque).
    private static function host(): string
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
    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
    }

    private static function warn(string $message): void
    {
        fwrite(STDERR, 'clickhouse: ' . $message . PHP_EOL);
    }
}

function on_job_start(string $job, string $scope): void
{
    ClickHouseMetrics::start($job, $scope);
}

function on_job_finish(string $status, string $job, string $reason = ''): void
{
    ClickHouseMetrics::finish($status, $job, $reason);
}

function ch_counter(string $metric, int $value = 1): void
{
    ClickHouseMetrics::counter($metric, $value);
}
