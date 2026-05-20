<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Benutzerverwaltung';
\App\Inc\require_admin();
$pdo = \App\Inc\db();

$allowedRoles = ['uploader', 'admin'];
$defaultRole = 'uploader';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	\App\Inc\csrf_verify_or_die();

	$action = (string) ($_POST['action'] ?? '');
	$userId = (int) ($_POST['user_id'] ?? 0);

	try {
		if ($action === 'create_user') {
			$email = trim((string) ($_POST['email'] ?? ''));
			$role = trim((string) ($_POST['role'] ?? $defaultRole));
			if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				throw new \RuntimeException('Ungültige E-Mail-Adresse.');
			}
			if (!in_array($role, $allowedRoles, true)) {
				$role = $defaultRole;
			}
			$password = \App\Inc\random_password(16);
			$hash = password_hash($password, PASSWORD_DEFAULT);
			$insert = $pdo->prepare('INSERT INTO wpl_users (email, password_hash, role) VALUES (?, ?, ?)');
			$insert->execute([$email, $hash, $role]);
			\App\Inc\flash_set('success', 'Benutzer angelegt: ' . $email . ' | Passwort: ' . $password);
			\App\Inc\redirect('/admin/users.php');
		}

		if ($action === 'reset_password') {
			$stmt = $pdo->prepare('SELECT id, email FROM wpl_users WHERE id = ? LIMIT 1');
			$stmt->execute([$userId]);
			$user = $stmt->fetch();
			if (! $user) {
				throw new \RuntimeException('Benutzer nicht gefunden.');
			}
			$password = \App\Inc\random_password(16);
			$hash = password_hash($password, PASSWORD_DEFAULT);
			$update = $pdo->prepare('UPDATE wpl_users SET password_hash = ? WHERE id = ?');
			$update->execute([$hash, $userId]);
			\App\Inc\flash_set('success', 'Neues Passwort für ' . $user['email'] . ': ' . $password);
			\App\Inc\redirect('/admin/users.php');
		}

		if ($action === 'set_role') {
			$role = trim((string) ($_POST['role'] ?? ''));
			if (!in_array($role, $allowedRoles, true)) {
				throw new \RuntimeException('Ungültige Rolle.');
			}
			$stmt = $pdo->prepare('SELECT id, email, role FROM wpl_users WHERE id = ? LIMIT 1');
			$stmt->execute([$userId]);
			$user = $stmt->fetch();
			if (! $user) {
				throw new \RuntimeException('Benutzer nicht gefunden.');
			}
			$update = $pdo->prepare('UPDATE wpl_users SET role = ? WHERE id = ?');
			$update->execute([$role, $userId]);
			\App\Inc\flash_set('success', 'Rolle geändert für ' . $user['email'] . ': ' . $role);
			\App\Inc\redirect('/admin/users.php');
		}

		throw new \RuntimeException('Unbekannte Aktion.');
	} catch (\Throwable $e) {
		\App\Inc\flash_set('error', 'Aktion fehlgeschlagen: ' . $e->getMessage());
		\App\Inc\redirect('/admin/users.php');
	}
}

$users = $pdo->query('SELECT id, email, role, created_at FROM wpl_users ORDER BY created_at DESC, id DESC')->fetchAll();
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Benutzerverwaltung</h1>

<h2>Neuen Benutzer anlegen</h2>
<form method="post">
	<?php echo \App\Inc\csrf_input(); ?>
	<input type="hidden" name="action" value="create_user">
	<label>
		E-Mail
		<input type="email" name="email" required>
	</label><br>
	<label>
		Rolle
		<select name="role">
			<option value="uploader" selected>uploader</option>
			<option value="admin">admin</option>
		</select>
	</label><br>
	<button type="submit">Benutzer anlegen</button>
</form>

<h2>Bestehende Benutzer</h2>
<table border="1" cellpadding="6" cellspacing="0">
	<tr>
		<th>E-Mail</th>
		<th>Rolle</th>
		<th>Erstellt</th>
		<th>Aktionen</th>
	</tr>
	<?php foreach ($users as $user): ?>
		<tr>
			<td><?php echo \App\Inc\h($user['email']); ?></td>
			<td><?php echo \App\Inc\h($user['role']); ?></td>
			<td><?php echo \App\Inc\h(\App\Inc\format_date_de((string) $user['created_at'], true)); ?></td>
			<td>
				<form method="post" style="display:inline-block;margin-right:0.5rem;">
					<?php echo \App\Inc\csrf_input(); ?>
					<input type="hidden" name="action" value="reset_password">
					<input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
					<button type="submit">Passwort zurücksetzen</button>
				</form>
				<form method="post" style="display:inline-block;">
					<?php echo \App\Inc\csrf_input(); ?>
					<input type="hidden" name="action" value="set_role">
					<input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
					<select name="role">
						<option value="uploader" <?php echo $user['role'] === 'uploader' ? 'selected' : ''; ?>>uploader</option>
						<option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
					</select>
					<button type="submit">Rolle ändern</button>
				</form>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
