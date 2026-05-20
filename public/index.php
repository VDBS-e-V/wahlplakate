<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/util.php';

$pageTitle = 'Wahlplakate';
$user = \App\Inc\current_user();
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>
<h1>Welcome to Wahlplakate</h1>
<?php if ($user): ?>
	<p>Signed in as <strong><?php echo \App\Inc\h($user['email']); ?></strong> (<?php echo \App\Inc\h($user['role']); ?>)</p>
	<ul>
		<li><a href="upload.php">Upload Image</a></li>
		<?php if (\App\Inc\is_admin()): ?>
			<li><a href="admin/imports.php">Admin Imports</a></li>
		<?php endif; ?>
	</ul>
<?php else: ?>
	<p>Not signed in. <a href="login.php">Login</a></p>
<?php endif; ?>
<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
