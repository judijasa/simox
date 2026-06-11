<?php

declare(strict_types=1);

namespace Utils\Logger;

function debug(string $message) {
// Usage: debug("This only prints in terminal");
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        // Only runs when executed from terminal
        echo "[DEBUG] " . $message . PHP_EOL;
    }
}
?>

