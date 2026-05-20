<?php
namespace App\Inc;

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

function current_user(): ?array
{
	if (empty($_SESSION['user_id'])) {
		return null;
	}
	$id = (int) $_SESSION['user_id'];
	$pdo = db();
	$stmt = $pdo->prepare('SELECT id, email, role FROM wpl_users WHERE id = ?');
	$stmt->execute([$id]);
	$user = $stmt->fetch();
	return $user ?: null;
}

function is_logged_in(): bool
{
	return current_user() !== null;
}

function require_login(): void
{
	if (! is_logged_in()) {
		$base = env('BASE_URL', null);
		if ($base) {
			header('Location: ' . rtrim($base, '/') . '/login.php');
		} else {
			header('Location: /login.php');
		}
		exit;
	}
}

function require_admin(): void
{
	$u = current_user();
	if (! $u || ($u['role'] ?? '') !== 'admin') {
		http_response_code(403);
		echo 'Forbidden';
		exit;
	}
}

function login(string $email, string $password): bool
{
	$pdo = db();
	$stmt = $pdo->prepare('SELECT id, email, password_hash, role FROM wpl_users WHERE email = ? LIMIT 1');
	$stmt->execute([$email]);
	$row = $stmt->fetch();
	if (! $row) {
		return false;
	}
	if (! isset($row['password_hash'])) {
		return false;
	}
	if (password_verify($password, $row['password_hash'])) {
		$_SESSION['user_id'] = $row['id'];
		return true;
	}
	return false;
}

function logout(): void
{
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000,
			$params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
}

