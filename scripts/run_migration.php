<?php
// Trigger DB connection which runs ensure_schema(), then report table existence
require_once __DIR__ . '/../app/inc/db.php';

use App\Inc as Inc;

try {
    $pdo = Inc\db();
    echo "Connected and db() invoked\n";
    $has = (bool)$pdo->query("SHOW TABLES LIKE 'wpl_users'")->fetchColumn();
    echo "wpl_users exists: " . ($has ? 'yes' : 'no') . "\n";
    if ($has) {
        $count = $pdo->query("SELECT COUNT(*) FROM wpl_users")->fetchColumn();
        echo "wpl_users rows: " . ($count === false ? 'N/A' : $count) . "\n";
    }
} catch (Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
