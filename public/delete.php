<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/db.php';
require_once __DIR__ . '/../app/inc/image_store.php';
require_once __DIR__ . '/../app/inc/csrf.php';
require_once __DIR__ . '/../app/inc/util.php';

$pageTitle = 'Delete Image';
\App\Inc\require_login();
$pdo = \App\Inc\db();

if (empty($_GET['id']) || ! ctype_digit((string)$_GET['id'])) {
    http_response_code(400);
    echo 'Invalid id';
    exit;
}
$id = (int) $_GET['id'];
$stmt = $pdo->prepare('SELECT id, uploaded_by, file_path FROM wpl_images WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$img = $stmt->fetch();
if (! $img) {
    http_response_code(404);
    echo 'Not found';
    exit;
}
$user = \App\Inc\current_user();
if (! $user) {
    http_response_code(403);
    exit;
}
$isAdmin = ($user['role'] ?? '') === 'admin';
if (! $isAdmin && (int)$img['uploaded_by'] !== (int)$user['id']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM wpl_images WHERE id = ?');
        $del->execute([$id]);
        $pdo->commit();
        \App\Inc\delete_stored_file($img['file_path']);
        \App\Inc\flash_set('success', 'Image deleted successfully!');
        \App\Inc\redirect(\App\Inc\base_url() ?: '/');
    } catch (\Throwable $e) {
        $pdo->rollBack();
        \App\Inc\flash_set('error', 'Delete failed: ' . $e->getMessage());
        \App\Inc\redirect('image_view.php?id=' . $id);
    }
}

// GET: show confirmation form
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>
<h1>Delete Image #<?php echo \App\Inc\h((string)$id); ?></h1>
<p>Are you sure you want to delete this image? This action cannot be undone.</p>
<form method="post">
	<?php echo \App\Inc\csrf_input(); ?>
	<button type="submit">Confirm Delete</button>
    <a href="image_view.php?id=<?php echo \App\Inc\h((string)$id); ?>">Cancel</a>
</form>
<?php require_once __DIR__ . '/../app/views/footer.php'; ?>

