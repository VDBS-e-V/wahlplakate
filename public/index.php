<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/util.php';

$pageTitle = 'Wahlplakate';
$user = \App\Inc\current_user();
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>
<h1>Wahlplakate</h1>
<?php if ($user): ?>
	<p>Angemeldet als <strong><?php echo \App\Inc\h($user['email']); ?></strong> (<?php echo \App\Inc\h($user['role']); ?>)</p>
	<ul>
		<li><a href="upload.php">Bild hochladen</a></li>
		<?php if (\App\Inc\is_admin()): ?>
			<li><a href="/admin/imports.php">Import-Dashboard</a></li>
			<li><a href="/admin/users.php">Benutzerverwaltung</a></li>
		<?php endif; ?>
	</ul>
<?php else: ?>
	<p>Nicht angemeldet. <a href="login.php">Anmelden</a></p>
<?php endif; ?>
<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
