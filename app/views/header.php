<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo isset($pageTitle) ? \App\Inc\h($pageTitle) : 'Wahlplakate'; ?></title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			line-height: 1.5;
			color: #333;
			margin: 0;
			padding: 1rem;
			background: #f5f5f5;
		}
		.container {
			max-width: 1200px;
			margin: 0 auto;
			background: white;
			padding: 1.5rem;
			border-radius: 4px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}
		.flash {
			padding: 1rem;
			margin-bottom: 1rem;
			border-radius: 4px;
			border-left: 4px solid;
		}
		.flash-success {
			background: #d4edda;
			color: #155724;
			border-color: #28a745;
		}
		.flash-error {
			background: #f8d7da;
			color: #721c24;
			border-color: #f5c6cb;
		}
		.flash-info {
			background: #d1ecf1;
			color: #0c5460;
			border-color: #bee5eb;
		}
		.nav {
			margin-bottom: 2rem;
			padding-bottom: 1rem;
			border-bottom: 1px solid #ddd;
		}
		.nav a {
			margin-right: 1rem;
			color: #007bff;
			text-decoration: none;
		}
		.nav a:hover {
			text-decoration: underline;
		}
		form {
			margin: 1rem 0;
		}
		label {
			display: block;
			margin: 0.5rem 0;
		}
		input, select, textarea, button {
			padding: 0.5rem;
			border: 1px solid #ccc;
			border-radius: 4px;
			font-size: 1rem;
		}
		button {
			background: #007bff;
			color: white;
			border: none;
			cursor: pointer;
		}
		button:hover {
			background: #0056b3;
		}
	</style>
	<link rel="stylesheet" href="/css/wpl-ui.css">
</head>
<body>
<?php foreach (\App\Inc\flash_get_all() as $msg): ?>
	<div class="flash flash-<?php echo \App\Inc\h($msg['type']); ?>">
		<?php echo \App\Inc\h($msg['msg']); ?>
	</div>
<?php endforeach; ?>
<div class="container">
	<div class="nav">
		<a href="/">Startseite</a>
		<?php if (\App\Inc\is_admin()): ?>
			<a href="/admin/elections.php">Wahlen</a>
			<a href="/admin/imports.php">Import-Dashboard</a>
			<a href="/admin/users.php">Benutzerverwaltung</a>
		<?php endif; ?>
		<?php if (\App\Inc\is_logged_in()): ?>
			<a href="/images.php">Bilderliste</a>
			<a href="/logout.php">Abmelden</a>
		<?php else: ?>
			<a href="/login.php">Anmelden</a>
		<?php endif; ?>
	</div>
