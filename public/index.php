<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/util.php';

$user = \App\Inc\current_user();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Wahlplakate</title></head>
<body>
<h1>Wahlplakate</h1>
<?php if ($user): ?>
	<p>Signed in as <?php echo \App\Inc\h($user['email']); ?> (<?php echo \App\Inc\h($user['role']); ?>)</p>
	<p><a href="logout.php">Logout</a></p>
<?php else: ?>
	<p>Not signed in. <a href="login.php">Login</a></p>
<?php endif; ?>
</body>
</html>
