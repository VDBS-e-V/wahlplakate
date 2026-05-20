<?php
namespace App\Inc;

/**
 * csrf_token(): string
 * Ensures session is active, generates or retrieves CSRF token
 */
function csrf_token(): string
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

/**
 * csrf_input(): string
 * Returns HTML hidden input with CSRF token
 */
function csrf_input(): string
{
	$token = csrf_token();
	return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * csrf_verify_or_die(): void
 * Verifies CSRF token for POST requests, exits with 400 on failure
 */
function csrf_verify_or_die(): void
{
	// Only verify for POST requests
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	// ensure session is active
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}

	$posted = $_POST['csrf_token'] ?? '';
	$session_token = $_SESSION['csrf_token'] ?? '';
	
	// require both tokens to be present and equal
	if ($posted === '' || $session_token === '' || !hash_equals($session_token, $posted)) {
		http_response_code(400);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Bad Request: invalid CSRF token';
		exit;
	}
}
