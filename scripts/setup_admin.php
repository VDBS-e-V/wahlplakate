<?php
// Setup or update admin user. Safe to run via browser or CLI.

$envFile = __DIR__ . '/../inc/env.php';
if (file_exists($envFile)) {
	require_once $envFile;
}
$dbFile = __DIR__ . '/../inc/db.php';
if (file_exists($dbFile)) {
	require_once $dbFile;
}
$utilFile = __DIR__ . '/../inc/util.php';
if (file_exists($utilFile)) {
	require_once $utilFile;
}

function output(string $s): void
{
	if (php_sapi_name() === 'cli') {
		echo $s . "\n";
	} else {
		echo nl2br(htmlspecialchars($s)) . "<br>";
	}
}

function get_env_val(string $key, $default = null)
{
	if (function_exists('\\App\\Inc\\env')) {
		return \App\Inc\env($key, $default);
	}

	// Find nearest .env by walking up from the script directory
	$envPath = null;
	$searchPath = realpath(__DIR__);
	for ($i = 0; $i < 6 && $searchPath !== false; $i++) {
		$candidate = $searchPath . DIRECTORY_SEPARATOR . '.env';
		if (is_readable($candidate)) {
			$envPath = $candidate;
			break;
		}
		$parent = dirname($searchPath);
		if ($parent === $searchPath) {
			break;
		}
		$searchPath = $parent;
	}
	if ($envPath === null) {
		return $default;
	}
	$content = file_get_contents($envPath);
	$lines = preg_split('/\r\n|\n|\r/', $content);
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || strpos($line, '#') === 0) {
			continue;
		}
		if (strpos($line, '=') === false) {
			continue;
		}
		[$k, $v] = explode('=', $line, 2);
		$k = trim($k);
		$v = trim($v);
		if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
			$v = substr($v, 1, -1);
		}
		if ($k === $key) {
			return $v;
		}
	}
	return $default;
}

$email = get_env_val('ADMIN_EMAIL', 'admin@vdb.schule');

if (function_exists('\\App\\Inc\\db')) {
	$pdo = \App\Inc\db();
} else {
	$dsn = get_env_val('DATABASE_URL', null) ?: get_env_val('DB_DSN', null) ?: get_env_val('DB_URL', null);
	$dbUser = get_env_val('DB_USER', get_env_val('DB_USERNAME', 'root'));
	$dbPass = get_env_val('DB_PASS', get_env_val('DB_PASSWORD', ''));

	if (empty($dsn)) {
		$dbHostRaw = get_env_val('DB_HOST', '127.0.0.1');
		$dbPort = get_env_val('DB_PORT', '3306');
		$dbName = get_env_val('DB_NAME', get_env_val('DB_DATABASE', 'wahlplakate'));
		$charset = get_env_val('DB_CHARSET', 'utf8mb4');

		// If DB_HOST contains a port (e.g. localhost:3307 or [::1]:3307), parse it.
		$dbHost = $dbHostRaw;
		if (preg_match('/^\[(.*)\]:(\d+)$/', $dbHostRaw, $m)) {
			// [IPv6]:port
			$dbHost = $m[1];
			$dbPort = $m[2];
		} elseif (preg_match('/^(.*):(\d+)$/', $dbHostRaw, $m)) {
			// host:port
			$dbHost = $m[1];
			$dbPort = $m[2];
		}

		$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$charset}";
	}

	try {
		output("Attempting PDO connect with DSN: $dsn");
		output("DB user: $dbUser");
		$pdo = new PDO($dsn, $dbUser, $dbPass, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_EMULATE_PREPARES => false,
		]);
	} catch (Exception $e) {
		output('Could not connect to database: ' . $e->getMessage());
		exit(1);
	}
}

// Table prefix from .env (DB_PREFIX or TABLE_PREFIX). Sanitize to safe chars.
$prefixRaw = get_env_val('DB_PREFIX', get_env_val('TABLE_PREFIX', 'wpl'));
$prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefixRaw);
if ($prefix !== '' && substr($prefix, -1) !== '_') {
	$prefix .= '_';
}
$usersTable = $prefix . 'users';

$stmt = $pdo->prepare("SELECT id FROM `{$usersTable}` WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$exists = $stmt->fetchColumn();
if ($exists) {
	output("Admin user already exists: $email");
	exit;
}

$initialPassword = get_env_val('ADMIN_INITIAL_PASSWORD', null);
$wrote = false;
$usedEnvPassword = false;
if (! empty($initialPassword)) {
	$password = $initialPassword;
	$usedEnvPassword = true;
} else {
	if (function_exists('\\App\\Inc\\random_password')) {
		$password = \App\Inc\random_password(16);
	} else {
		$len = 16;
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*()-_=+';
		$max = strlen($chars) - 1;
		$pw = '';
		for ($i = 0; $i < $len; $i++) {
			$pw .= $chars[random_int(0, $max)];
		}
		$password = $pw;
	}
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare("INSERT INTO `{$usersTable}` (email, password_hash, role) VALUES (?, ?, ?)");
$insert->execute([$email, $hash, 'admin']);

if (! $usedEnvPassword) {
	$root = realpath(__DIR__ . '/../../');
	$envPath = $root . DIRECTORY_SEPARATOR . '.env';
	$line = "ADMIN_INITIAL_PASSWORD={$password}";
	if (is_writable($envPath)) {
		$content = file_get_contents($envPath);
		if (strpos($content, 'ADMIN_INITIAL_PASSWORD=') !== false) {
			$content = preg_replace('/^ADMIN_INITIAL_PASSWORD=.*$/m', $line, $content);
		} else {
			$content .= PHP_EOL . $line . PHP_EOL;
		}
		$wrote = (bool) file_put_contents($envPath, $content);
	}
}

output("Created admin: $email");
output("Password: $password");
if ($usedEnvPassword) {
	output("Used ADMIN_INITIAL_PASSWORD from .env");
} elseif ($wrote) {
	output("Wrote ADMIN_INITIAL_PASSWORD into .env");
} else {
	output("Could not write .env — please add the following line to your .env file:");
	output($line);
}

