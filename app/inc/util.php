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

