<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/csv.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Import Parties';
\App\Inc\require_admin();
$pdo = \App\Inc\db();

$stats = ['inserted' => 0, 'updated' => 0, 'errors' => 0];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    $ensureParteilos = isset($_POST['ensure_parteilos']) && $_POST['ensure_parteilos'] !== '0';

    try {
        $rows = \App\Inc\read_csv_uploaded('csv_file');

        if (empty($rows)) {
            throw new \RuntimeException('CSV file is empty');
        }

        $existingPartylos = $pdo->prepare('SELECT 1 FROM parties WHERE code = ? LIMIT 1');
        $existingPartylos->execute(['PARTEILOS']);
        $parteilosExists = (bool) $existingPartylos->fetchColumn();

        $csvHasParteilos = false;
        foreach ($rows as $row) {
            if (trim((string) ($row['party_code'] ?? '')) === 'PARTEILOS') {
                $csvHasParteilos = true;
                break;
            }
        }

        if (! $parteilosExists && ! $csvHasParteilos) {
            throw new \RuntimeException('Party code PARTEILOS fehlt im CSV und ist noch nicht in der Datenbank vorhanden');
        }

        foreach ($rows as $row) {
            try {
                $name = trim((string) ($row['party_name'] ?? ''));
                $code = trim((string) ($row['party_code'] ?? ''));

                if ($name === '' || $code === '') {
                    $stats['errors']++;
                    continue;
                }

                $stmt = $pdo->prepare('SELECT id FROM parties WHERE code = ? LIMIT 1');
                $stmt->execute([$code]);
                $existing = $stmt->fetchColumn();

                if ($existing) {
                    $upd = $pdo->prepare('UPDATE parties SET name = ? WHERE code = ?');
                    $upd->execute([$name, $code]);
                    $stats['updated']++;
                } else {
                    $ins = $pdo->prepare('INSERT INTO parties (name, code) VALUES (?, ?)');
                    $ins->execute([$name, $code]);
                    $stats['inserted']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                    $errors[] = $e->getMessage();
            }
        }

        if ($ensureParteilos) {
            $stmt = $pdo->prepare('SELECT id FROM parties WHERE code = ? LIMIT 1');
            $stmt->execute(['PARTEILOS']);
            if (! $stmt->fetchColumn()) {
                $ins = $pdo->prepare('INSERT INTO parties (name, code) VALUES (?, ?)');
                $ins->execute(['Parteilos', 'PARTEILOS']);
                $stats['inserted']++;
            }
        }

        $summary = "Import complete: {$stats['inserted']} inserted, {$stats['updated']} updated, {$stats['errors']} errors";
        \App\Inc\flash_set('success', $summary);
        \App\Inc\redirect('/admin/import_parties.php');

    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Import failed: ' . $e->getMessage());
        \App\Inc\redirect('/admin/import_parties.php');
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Import Parties</h1>
<p><strong>CSV Format:</strong> party_name; party_code; party_short (optional, currently ignored)</p>
<p><strong>Note:</strong> PARTEILOS is auto-created by default if missing.</p>
<form method="post" enctype="multipart/form-data">
    <?php echo \App\Inc\csrf_input(); ?>
    <label>
        CSV File:
        <input type="file" name="csv_file" accept=".csv" required>
    </label><br>
    <label>
        <input type="checkbox" name="ensure_parteilos" value="1" checked>
        PARTEILOS automatisch anlegen
    </label><br>
    <button type="submit">Import</button>
</form>
<?php if ($errors !== []): ?>
    <h2>Erste Fehler</h2>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr><th>#</th><th>Message</th></tr>
        <?php foreach (array_slice($errors, 0, 10) as $index => $message): ?>
            <tr><td><?php echo $index + 1; ?></td><td><?php echo \App\Inc\h($message); ?></td></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<a href="/admin/imports.php">Back to Imports</a>
<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
