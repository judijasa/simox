<?php

declare(strict_types=1);

require 'vendor/autoload.php';

use Utils\Agent;
use Utils\CronJob;
use Utils\Logger;

#[CronJob(schedule: '*/5 * * * *')]
#[Agent(dbTarget: null)]
function memory_cleaning(): void
{
    $threshold = 90;
    $used_ram  = (int) shell_exec("free | awk '/Mem:/ {printf \"%.0f\", $3/$2 * 100}'");

    if ($used_ram < $threshold) return;

    Logger::info("RAM usage at {$used_ram}%, exceeding threshold. Finding and killing top memory consumer...");

    $top_pid = (int) shell_exec("ps -eo pid,%mem,cmd --sort=-%mem | awk 'NR==2 {print $1}'");

    if ($top_pid > 0) {
        Logger::info("Killing process $top_pid.");
        posix_kill($top_pid, SIGKILL);
    } else {
        Logger::info("No process found, rebooting as last resort...");
        shell_exec('sudo reboot');
    }
}

#[CronJob(schedule: '0 6 * * 6')]
#[Agent(dbTarget: null)]
function trim_log_files(): void
{
    // phprun (wrapped in flake.nix) always provides PHPRUN_LOG_PATH.
    $log_dir = getenv('PHPRUN_LOG_PATH');
    if ($log_dir === false || $log_dir === '') {
        Logger::info('PHPRUN_LOG_PATH not set. Skipping log trimming.');
        return;
    }
    $max_size = 1_000_000; // 1MB per log file

    foreach (glob("$log_dir/*.log") as $log_file) {
        if (filesize($log_file) <= $max_size) continue;

        Logger::info("Trimming $log_file...");
        $lines     = file($log_file);
        $half      = array_slice($lines, (int) (count($lines) / 2));
        file_put_contents($log_file, implode('', $half));
        Logger::info("Trimmed $log_file to half its original size.");
    }
}

#[CronJob(schedule: '0 8 * * 6')]
#[Agent(dbTarget: null)]
function nix_store_gc(): void
{
    // The nix store grows with every nixpkgs revision shipped by deploy.sh;
    // only the latest closure is gcroot-protected, so old ones accumulate.
    $nix_store = locate_nix_store();
    if ($nix_store === null) {
        Logger::info('nix-store binary not found. Skipping nix store garbage collection.');
        return;
    }

    run_nix_store_command($nix_store, '--gc');
    // --optimise hard-links duplicated store paths (eg same package built
    // from different nixpkgs revisions). Disable with SIMOX_NIX_GC_OPTIMISE=0.
    if (getenv('SIMOX_NIX_GC_OPTIMISE') !== '0') {
        run_nix_store_command($nix_store, '--optimise');
    }
}

/**
 * Locates the nix-store binary. deploy.sh symlinks it into /usr/local/bin;
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

