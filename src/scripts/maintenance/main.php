<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Logger;

#[CronJob(schedule: '0 6 * * 6', scope: 'host')]
#[Agent(dbTarget: null)]
function trim_log_files(): void
// Keeps the newest 5MB of every log file in REPO_LOG.
{
    // The repo-root .env (loaded by the nix phprun wrapper) provides REPO_LOG.
    $log_dir = getenv('REPO_LOG');
    if ($log_dir === false || $log_dir === '') {
        Logger::info('REPO_LOG not set. Skipping log trimming.');
        return;
    }

    // The cap is the retention policy: no log keeps more than this, so per-log
    // disk use is bounded by one stated number. Halving the line count of files
    // over a trigger size instead made the retained size an accident of the
    // trigger, and left long-line logs near full size.
    $max_bytes = 5_000_000;

    $trimmed   = 0;
    $reclaimed = 0;
    foreach (glob("$log_dir/*.log") ?: [] as $log_file) {
        $freed = trim_log_file($log_file, $max_bytes);
        if ($freed === 0) continue;

        Logger::info("Trimmed $log_file ($freed bytes reclaimed; cap $max_bytes).");
        $trimmed++;
        $reclaimed += $freed;
    }

    ch_counter('logs_trimmed', $trimmed);
    ch_counter('log_bytes_reclaimed', $reclaimed);
}

/**
 * Keeps the newest $max_bytes bytes of $log_file, starting at a line boundary.
 * Returns the bytes reclaimed, or 0 when the file is under the cap or the trim
 * could not be applied.
 *
 * The file is rewritten in place rather than replaced: phprun opens its log
 * with O_APPEND, so the run's next write lands at the new EOF, and the inode
 * keeps its owner and mode (cron runs as root; a manual run does not).
 */
function trim_log_file(string $log_file, int $max_bytes): int
{
    clearstatcache(true, $log_file);
    $size = filesize($log_file);
    if ($size === false || $size <= $max_bytes) return 0;

    // Read only the tail: the cap bounds memory use, whatever the file size.
    $handle = fopen($log_file, 'r+b');
    if ($handle === false) return 0;

    fseek($handle, $size - $max_bytes);
    $tail = stream_get_contents($handle);
    if ($tail === false || $tail === '') {
        fclose($handle);
        return 0;
    }

    // Drop the partial first line so the kept log starts on a whole record.
    $newline = strpos($tail, "\n");
    if ($newline !== false) {
        $tail = substr($tail, $newline + 1);
    }

    // Write before truncating: a short write leaves the file at its old length
    // (its prefix overwritten) instead of emptying a log on a full disk.
    $length  = strlen($tail);
    fseek($handle, 0);
    $written = fwrite($handle, $tail);
    $ok      = $written === $length && ftruncate($handle, $length);
    fclose($handle);

    return $ok ? $size - $length : 0;
}

#[CronJob(schedule: '0 8 * * 6', scope: 'host')]
#[Agent(dbTarget: null)]
function nix_store_gc(): void
// Runs nix-store GC, then --optimise unless NIX_GC_OPTIMISE=0.
{
    // The nix store grows with every nixpkgs revision shipped by deploy;
    // only the latest closure is gcroot-protected, so old ones accumulate.
    $nix_store = locate_nix_store();
    if ($nix_store === null) {
        Logger::info('nix-store binary not found. Skipping nix store garbage collection.');
        return;
    }

    run_nix_store_command($nix_store, '--gc');
    // --optimise hard-links duplicated store paths (eg same package built
    // from different nixpkgs revisions). Disable with NIX_GC_OPTIMISE=0.
    if (getenv('NIX_GC_OPTIMISE') !== '0') {
        run_nix_store_command($nix_store, '--optimise');
    }
}

/**
 * Locates the nix-store binary. deploy symlinks it into /usr/local/bin;
 * fall back to the canonical multi-user nix path.
 */
function locate_nix_store(): ?string
{
    $candidates = [
        '/usr/local/bin/nix-store',
        '/nix/var/nix/profiles/default/bin/nix-store',
    ];

    foreach ($candidates as $path) {
        if (is_executable($path)) return $path;
    }

    return null;
}

/**
 * Runs a nix-store action (--gc, --optimise) and logs its output.
 */
function run_nix_store_command(string $nix_store, string $action): void
{
    Logger::info("Running nix-store $action...");
    $output    = [];
    $exit_code = -1;
    exec(escapeshellarg($nix_store) . " $action 2>&1", $output, $exit_code);
    $message = trim(implode(PHP_EOL, $output));
    Logger::info($message !== '' ? $message : "nix-store $action exited with code $exit_code.");
}
