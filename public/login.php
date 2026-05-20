<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/util.php';
require_once __DIR__ . '/../app/inc/csrf.php';

$pageTitle = 'Login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		\App\Inc\csrf_verify_or_die();
		$email = $_POST['email'] ?? '';
		$password = $_POST['password'] ?? '';
		if (\App\Inc\login($email, $password)) {
				\App\Inc\flash_set('success', 'Login successful!');
				\App\Inc\redirect(\App\Inc\base_url() ?: '/');
		}
		\App\Inc\flash_set('error', 'Invalid email or password');
		\App\Inc\redirect('login.php');
}
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>
<h1>Login</h1>
<form method="post">
	<?php echo \App\Inc\csrf_input(); ?>
	<label>Email: <input type="email" name="email" required></label><br>
	<label>Password: <input type="password" name="password" required></label><br>
	<button type="submit">Login</button>
</form>
<?php require_once __DIR__ . '/../app/views/footer.php'; ?>

