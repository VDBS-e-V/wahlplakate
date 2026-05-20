<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/db.php';
require_once __DIR__ . '/../app/inc/image_store.php';

\App\Inc\require_login();
$pdo = \App\Inc\db();

if (empty($_GET['id']) || ! ctype_digit((string)$_GET['id'])) {
    http_response_code(400);
    echo 'Invalid id';
    exit;
}
$id = (int) $_GET['id'];
$stmt = $pdo->prepare('SELECT id, uploaded_by, file_path FROM images WHERE id = ? LIMIT 1');
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

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (! isset($_FILES['image'])) {
            throw new \RuntimeException('No file uploaded');
        }
        $validated = \App\Inc\validate_upload($_FILES['image']);
        // check duplicate sha
        $dup = $pdo->prepare('SELECT id FROM images WHERE sha256 = ? AND id != ? LIMIT 1');
        $dup->execute([$validated['sha256'], $id]);
        $existing = $dup->fetchColumn();
        if ($existing) {
            throw new \RuntimeException('Duplicate image exists with id ' . $existing);
        }

        $stored = \App\Inc\store_upload($validated);

        $upd = $pdo->prepare('UPDATE images SET file_path = ?, original_filename = ?, mime = ?, size_bytes = ?, sha256 = ? WHERE id = ?');
        $upd->execute([$stored['file_path'], $_FILES['image']['name'], $validated['mime'], $validated['size_bytes'], $validated['sha256'], $id]);

        // delete old file
        \App\Inc\delete_stored_file($img['file_path']);

        header('Location: image.php?id=' . $id);
        exit;
    } catch (\Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Replace Image</title></head>
<body>
<h1>Replace Image #<?php echo \App\Inc\h((string)$id); ?></h1>
<?php foreach ($errors as $err): ?>
  <p style="color:red"><?php echo \App\Inc\h($err); ?></p>
<?php endforeach; ?>
<form method="post" enctype="multipart/form-data">
  <label>New Image: <input type="file" name="image" accept="image/jpeg,image/png" required></label><br>
  <button type="submit">Replace</button>
</form>
</body>
</html>
