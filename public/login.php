<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/util.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$email = $_POST['email'] ?? '';
		$password = $_POST['password'] ?? '';
		if (\App\Inc\login($email, $password)) {
				$base = \App\Inc\base_url() ?: '';
				header('Location: ' . ($base ?: 'index.php'));
				exit;
		}
		$error = 'Invalid credentials';
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Login</title></head>
<body>
<h1>Login</h1>
<?php if ($error): ?><p style="color:red"><?php echo \App\Inc\h($error); ?></p><?php endif; ?>
<form method="post">
	<label>Email: <input type="email" name="email" required></label><br>
	<label>Password: <input type="password" name="password" required></label><br>
	<button type="submit">Login</button>
</form>
</body>
</html>

