<?php

declare(strict_types=1);

require 'vendor/autoload.php';
require 'src/utils/attributes.php';

use Utils\Logger;

#[CronJob(schedule: '5min')]
#[Agent]
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

#[CronJob(schedule: 'monthly')]
#[Agent]
function trim_log_files(): void
{
    $log_dir  = getenv('SIMO_LOG_PATH');
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
