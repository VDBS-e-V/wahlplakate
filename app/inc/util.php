<?php
namespace App\Inc;

require_once __DIR__ . '/env.php';

function redirect(string $path): void
{
	// if path looks absolute URL, use it; if starts with '/', treat as root under BASE_URL
	if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
		header('Location: ' . $path);
		exit;
	}
	$base = env('BASE_URL', null);
	if ($base && str_starts_with($path, '/')) {
		header('Location: ' . rtrim($base, '/') . $path);
		exit;
	}
	// relative path
	header('Location: ' . $path);
	exit;
}

function h(string $s): string
{
	return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function random_token(int $bytes = 32): string
{
	return bin2hex(random_bytes($bytes));
}

function random_password(int $len = 16): string
{
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
	$max = strlen($chars) - 1;
	$password = '';
	for ($i = 0; $i < $len; $i++) {
		$password .= $chars[random_int(0, $max)];
	}
	return $password;
}

function base_url(): ?string
{
	return env('BASE_URL', null);
}

function flash_set(string $type, string $msg): void
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	if (!isset($_SESSION['flash'])) {
		$_SESSION['flash'] = [];
	}
	$_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_get_all(): array
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	$messages = $_SESSION['flash'] ?? [];
	$_SESSION['flash'] = [];
	return $messages;
}

function is_admin(): bool
{
	$user = current_user();
	return $user !== null && ($user['role'] ?? '') === 'admin';
}

function format_date_de(?string $value, bool $withTime = false): string
{
	if ($value === null || $value === '') {
		return '';
	}
	try {
		$dt = new \DateTime($value);
	} catch (\Exception $e) {
		return (string) $value;
	}
	if ($withTime) {
		return $dt->format('d.m.Y H:i');
	}
	return $dt->format('d.m.Y');
}

/**
 * Parse user-supplied date (DE `DD.MM.YYYY` or ISO `YYYY-MM-DD`) into ISO `YYYY-MM-DD` or null on failure.
 */
function parse_date_input_to_iso(string $value): ?string
{
	$s = trim($value);
	if ($s === '') {
		return null;
	}
	// ISO yyyy-mm-dd
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
		return $s;
	}
	// German dd.mm.yyyy
	if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{4}$/', $s)) {
		[$d, $m, $y] = explode('.', $s);
		$d = str_pad($d, 2, '0', STR_PAD_LEFT);
		$m = str_pad($m, 2, '0', STR_PAD_LEFT);
		if (checkdate((int) $m, (int) $d, (int) $y)) {
			return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
		}
		return null;
	}
	// Try flexible parse
	try {
		$dt = new \DateTime($s);
		return $dt->format('Y-m-d');
	} catch (\Exception $e) {
		return null;
	}
}

function set_default_election_id(?int $id): void
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	if ($id === null || $id === 0) {
		unset($_SESSION['default_election_id']);
	} else {
		$_SESSION['default_election_id'] = (int) $id;
	}
}

function get_default_election_id(): ?int
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	return isset($_SESSION['default_election_id']) ? (int) $_SESSION['default_election_id'] : null;
}

function clear_default_election_id(): void
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	unset($_SESSION['default_election_id']);
}

function require_election_selected(?int $fallbackElectionId = null): int
{
	$electionId = (int) ($_GET['election_id'] ?? ($_POST['election_id'] ?? 0));
	if ($electionId <= 0 && $fallbackElectionId !== null) {
		$electionId = $fallbackElectionId;
	}
	if ($electionId <= 0) {
		$pdo = db();
		$first = $pdo->query('SELECT id FROM elections ORDER BY id DESC LIMIT 1')->fetchColumn();
		$electionId = $first ? (int) $first : 0;
	}
	if ($electionId <= 0) {
		flash_set('error', 'Bitte zuerst eine Wahl auswählen.');
		redirect('/admin/imports.php');
	}
	$pdo = db();
	$stmt = $pdo->prepare('SELECT id FROM elections WHERE id = ? LIMIT 1');
	$stmt->execute([$electionId]);
	if (! $stmt->fetchColumn()) {
		flash_set('error', 'Die gewählte Wahl existiert nicht.');
		redirect('/admin/imports.php');
	}
	return $electionId;
}

