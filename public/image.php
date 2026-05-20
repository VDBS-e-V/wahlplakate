<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/env.php';

\App\Inc\require_login();

if (empty($_GET['id']) || ! ctype_digit((string)$_GET['id'])) {
	http_response_code(400);
	echo 'Invalid id';
	exit;
}
$id = (int) $_GET['id'];
require_once __DIR__ . '/../app/inc/db.php';
$pdo = \App\Inc\db();
$stmt = $pdo->prepare('SELECT file_path, mime, original_filename FROM images WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (! $row) {
	http_response_code(404);
	echo 'Not found';
	exit;
}
$uploadDir = \App\Inc\env_required('UPLOAD_DIR');
$abs = rtrim($uploadDir, "\\/") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $row['file_path']);
if (! is_file($abs)) {
	http_response_code(404);
	echo 'File not found';
	exit;
}
$size = filesize($abs);
header('Content-Type: ' . $row['mime']);
header('Content-Length: ' . ($size !== false ? $size : 0));
// safe filename
$fn = basename($row['original_filename']);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $fn) . '"');
header('X-Content-Type-Options: nosniff');
readfile($abs);
exit;

