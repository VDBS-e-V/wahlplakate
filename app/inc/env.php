<?php
// Simple .env loader
// Loads variables from project root .env into memory and provides helpers

namespace App\Inc;

/** @var array<string,string|null> */
$_ENV_VARS = [];

function load_env_file(): void
{
	global $_ENV_VARS;
	static $loaded = false;
	if ($loaded) {
		return;
	}
	$loaded = true;

	$root = realpath(__DIR__ . '/../../');
	if (! $root) {
		return;
	}
	$path = $root . DIRECTORY_SEPARATOR . '.env';
	if (! is_file($path)) {
		return;
	}

	$lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if ($lines === false) {
		return;
	}
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
			continue;
		}
		if (strpos($line, '=') === false) {
			continue;
		}
		[$k, $v] = explode('=', $line, 2);
		$k = trim($k);
		$v = isset($v) ? trim($v) : null;
		// remove surrounding quotes
		if ($v !== null && strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
			$v = substr($v, 1, -1);
		}
		$_ENV_VARS[$k] = $v;
	}
	// normalize BASE_URL (no trailing slash)
	if (! empty($_ENV_VARS['BASE_URL'])) {
		$_ENV_VARS['BASE_URL'] = rtrim($_ENV_VARS['BASE_URL'], '/') ;
	}
}

function env(string $key, $default = null): ?string
{
	global $_ENV_VARS;
	load_env_file();
	if (array_key_exists($key, $_ENV_VARS)) {
		return $_ENV_VARS[$key];
	}
	return $default;
}

function env_required(string $key): string
{
	$v = env($key, null);
	if ($v === null || $v === '') {
		throw new \RuntimeException("Environment variable $key is required");
	}
	return $v;
}

// bootstrap on include
load_env_file();
