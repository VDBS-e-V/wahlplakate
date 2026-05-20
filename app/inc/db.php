<?php
namespace App\Inc;

require_once __DIR__ . '/env.php';

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
	return $pdo;
}

