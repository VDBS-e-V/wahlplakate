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

function base_url(): ?string
{
	return env('BASE_URL', null);
}

