<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/db.php';
require_once __DIR__ . '/../app/inc/util.php';

\App\Inc\require_login();
$pdo = \App\Inc\db();

if (empty($_GET['id']) || ! ctype_digit((string)$_GET['id'])) {
    http_response_code(400);
    echo 'Invalid id';
    exit;
}
$id = (int) $_GET['id'];

$stmt = $pdo->prepare('SELECT i.id,i.file_path,i.original_filename,i.created_at,e.name AS election_name,l.name AS locality_name,ep.ballot_label AS party_label,ec.name AS candidate_name FROM wpl_images i LEFT JOIN wpl_elections e ON i.election_id = e.id LEFT JOIN wpl_localities l ON i.locality_id = l.id LEFT JOIN wpl_election_parties ep ON i.election_party_id = ep.id LEFT JOIN wpl_election_candidates ec ON i.election_candidate_id = ec.id WHERE i.id = ? LIMIT 1');
$stmt->execute([$id]);
$img = $stmt->fetch();
if (! $img) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$createdAt = $img['created_at'] ?? '1970-01-01 00:00:00';

// previous
$prevStmt = $pdo->prepare('SELECT id FROM wpl_images WHERE (created_at < ? OR (created_at = ? AND id < ?)) ORDER BY created_at DESC, id DESC LIMIT 1');
$prevStmt->execute([$createdAt, $createdAt, $id]);
$prevId = $prevStmt->fetchColumn();

// next
$nextStmt = $pdo->prepare('SELECT id FROM wpl_images WHERE (created_at > ? OR (created_at = ? AND id > ?)) ORDER BY created_at ASC, id ASC LIMIT 1');
$nextStmt->execute([$createdAt, $createdAt, $id]);
$nextId = $nextStmt->fetchColumn();

$pageTitle = 'Bild ' . $id;
require_once __DIR__ . '/../app/views/header.php';
?>
<div class="image-controls">
    <?php if ($prevId): ?>
        <a class="btn btn-outline" href="image_view.php?id=<?php echo \App\Inc\h((string)$prevId); ?>">&laquo; Zurück</a>
    <?php else: ?>
        <button class="btn btn-outline" disabled>&laquo; Zurück</button>
    <?php endif; ?>

    <?php if ($nextId): ?>
        <a class="btn btn-outline" href="image_view.php?id=<?php echo \App\Inc\h((string)$nextId); ?>">Weiter &raquo;</a>
    <?php else: ?>
        <button class="btn btn-outline" disabled>Weiter &raquo;</button>
    <?php endif; ?>

    <a class="btn" href="images.php">Close</a>
    <a class="btn btn-danger" href="delete.php?id=<?php echo \App\Inc\h((string)$img['id']); ?>">Löschen</a>
</div>

<div class="image-full">
    <img class="image-full__img" src="image.php?id=<?php echo \App\Inc\h((string)$img['id']); ?>" alt="">
</div>

<div class="image-meta">
    <div><strong>Wahl:</strong> <?php echo \App\Inc\h($img['election_name'] ?? ''); ?></div>
    <div><strong>Ortsteil:</strong> <?php echo \App\Inc\h($img['locality_name'] ?? ''); ?></div>
    <div><strong>Partei:</strong> <?php echo \App\Inc\h($img['party_label'] ?? ''); ?></div>
    <div><strong>Kandidat*in:</strong> <?php echo \App\Inc\h($img['candidate_name'] ?? ''); ?></div>
    <div><strong>Originaldatei:</strong> <?php echo \App\Inc\h($img['original_filename'] ?? ''); ?></div>
    <div><small>hochgeladen: <?php echo \App\Inc\h((string)\App\Inc\format_date_de($img['created_at'] ?? '')); ?></small></div>
</div>

<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
