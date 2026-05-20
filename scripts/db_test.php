<?php
// Quick DB diagnostic for local debugging via CLI
require_once __DIR__ . '/../app/inc/env.php';

use App\Inc as Env;

$hostRaw = Env\env('DB_HOST', '127.0.0.1');
$port = Env\env('DB_PORT', null);
$name = Env\env_required('DB_NAME');
$user = Env\env('DB_USER', 'root');
$pass = Env\env('DB_PASS', '');

// parse host:port
$host = $hostRaw;
if (preg_match('/^\[(.*)\]:(\d+)$/', $hostRaw, $m)) {
    $host = $m[1];
    $port = $m[2];
} elseif (preg_match('/^(.*):(\d+)$/', $hostRaw, $m)) {
    $host = $m[1];
    $port = $m[2];
}

$dsn = "mysql:host={$host};";
if (! empty($port)) {
    $dsn .= "port={$port};";
}
$dsn .= "dbname={$name};charset=utf8mb4";

echo "DSN: $dsn\n";
echo "User: $user\n";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Connected successfully\n";
    $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "Server version: " . $ver . "\n";
} catch (PDOException $e) {
    echo "PDO Exception: " . $e->getMessage() . "\n";
    if (isset($e->errorInfo)) {
        var_export($e->errorInfo);
        echo "\n";
    }
}
