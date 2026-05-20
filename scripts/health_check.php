<?php
// Quick health checks for local dev environment
require_once __DIR__ . '/../inc/env.php';
require_once __DIR__ . '/../inc/db.php';

echo "BASE_URL=" . \App\Inc\env('BASE_URL') . PHP_EOL;
try {
    $pdo = \App\Inc\db();
    echo "DB_OK" . PHP_EOL;
} catch (Throwable $e) {
    echo "DB_ERR:" . $e->getMessage() . PHP_EOL;
}

// simple helper to print divider
function out($s) { echo "--- $s ---" . PHP_EOL; }

out('END');
