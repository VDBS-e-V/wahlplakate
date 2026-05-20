<?php
namespace App\Inc;

require_once __DIR__ . '/env.php';

function exec_sql_script(\PDO $pdo, string $sql): void
{
	foreach (preg_split('/;\s*(?:\r?\n|$)/', trim($sql)) as $statement) {
		$statement = trim($statement);
		if ($statement === '' || str_starts_with($statement, '--')) {
			continue;
		}
		$pdo->exec($statement);
	}
}

function ensure_schema(\PDO $pdo): void
{
	$check = $pdo->query("SHOW TABLES LIKE 'users'");
	if ($check && $check->fetchColumn()) {
		return;
	}

	$schemaPath = __DIR__ . '/../scripts/migrate.sql';
	if (! is_file($schemaPath)) {
		return;
	}

	$sql = file_get_contents($schemaPath);
	if ($sql === false) {
		return;
	}

	exec_sql_script($pdo, $sql);
}

function ensure_wahl_scoped_schema(\PDO $pdo): void
{
	$check = $pdo->query("SHOW TABLES LIKE 'election_parties'");
	if ($check && $check->fetchColumn()) {
		return;
	}

	$schemaPath = __DIR__ . '/../scripts/migrate_wahl_scoped.sql';
	if (! is_file($schemaPath)) {
		return;
	}

	$sql = file_get_contents($schemaPath);
	if ($sql === false) {
		return;
	}

	exec_sql_script($pdo, $sql);
}

function db(): \PDO
{
	static $pdo = null;
	if ($pdo instanceof \PDO) {
		return $pdo;
	}

	$host = env('DB_HOST', '127.0.0.1');
	$name = env_required('DB_NAME');
	$user = env('DB_USER', 'root');
	$pass = env('DB_PASS', '');

	$dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
	$options = [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		\PDO::ATTR_EMULATE_PREPARES => false,
	];
	$pdo = new \PDO($dsn, $user, $pass, $options);
	ensure_schema($pdo);
	return $pdo;
}

