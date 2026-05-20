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

$pdo->beginTransaction();
try {
    $del = $pdo->prepare('DELETE FROM images WHERE id = ?');
    $del->execute([$id]);
    $pdo->commit();
    \App\Inc\delete_stored_file($img['file_path']);
    header('Location: ' . (\App\Inc\base_url() ?: '/'));
    exit;
} catch (\Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo 'Delete failed';
    exit;
}
