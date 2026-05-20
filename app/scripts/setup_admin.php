<?php
// Setup or update admin user. Safe to run via browser or CLI.

require_once __DIR__ . '/../inc/env.php';
require_once __DIR__ . '/../inc/db.php';

function output(string $s): void
{
	if (php_sapi_name() === 'cli') {
		echo $s . "\n";
	} else {
		echo nl2br(htmlspecialchars($s)) . "<br>";
	}
}

$email = env('ADMIN_EMAIL', 'admin@vdb.schule');
$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$exists = $stmt->fetchColumn();
if ($exists) {
	output("Admin user already exists: $email");
	exit;
}

// generate random password (16 chars, url-safe)
$bytes = random_bytes(12);
$password = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

$hash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
$insert->execute([$email, $hash, 'admin']);

// try to write ADMIN_INITIAL_PASSWORD into .env
$root = realpath(__DIR__ . '/../../');
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
$line = "ADMIN_INITIAL_PASSWORD={$password}";
$wrote = false;
if (is_writable($envPath)) {
	$content = file_get_contents($envPath);
	if (strpos($content, 'ADMIN_INITIAL_PASSWORD=') !== false) {
		$content = preg_replace('/^ADMIN_INITIAL_PASSWORD=.*$/m', $line, $content);
	} else {
		$content .= PHP_EOL . $line . PHP_EOL;
	}
	$wrote = (bool) file_put_contents($envPath, $content);
}

output("Created admin: $email");
output("Password: $password");
if ($wrote) {
	output("Wrote ADMIN_INITIAL_PASSWORD into .env");
} else {
	output("Could not write .env — please add the following line to your .env file:");
	output($line);
}

