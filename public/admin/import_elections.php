<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/csv.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Import Elections';
\App\Inc\require_admin();
$pdo = \App\Inc\db();

$stats = ['created' => 0, 'updated' => 0, 'errors' => 0, 'messages' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    
    try {
        $rows = \App\Inc\read_csv_uploaded('csv_file');

        if (empty($rows)) {
            throw new \RuntimeException('CSV file is empty');
        }

        foreach ($rows as $row) {
            try {
                $name = trim((string) ($row['election_name'] ?? ''));
                $date = trim((string) ($row['election_date'] ?? ''));

                if ($name === '') {
                    $stats['errors']++;
                    continue;
                }

                $date_val = null;
                if ($date !== '') {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        $date_val = $date;
                    } else {
                        throw new \RuntimeException('Invalid date format: ' . $date);
                    }
                }

                $stmt = $pdo->prepare('SELECT id FROM elections WHERE name = ? LIMIT 1');
                $stmt->execute([$name]);
                $existing = $stmt->fetchColumn();

                if ($existing) {
                    $upd = $pdo->prepare('UPDATE elections SET start_date = ? WHERE name = ?');
                    $upd->execute([$date_val, $name]);
                    $stats['updated']++;
                } else {
                    $ins = $pdo->prepare('INSERT INTO elections (name, start_date) VALUES (?, ?)');
                    $ins->execute([$name, $date_val]);
                    $stats['created']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }
        
        $summary = "Import complete: {$stats['created']} created, {$stats['updated']} updated, {$stats['errors']} errors";
        \App\Inc\flash_set('success', $summary);
        \App\Inc\redirect('/admin/import_elections.php');
        
    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Import failed: ' . $e->getMessage());
        \App\Inc\redirect('/admin/import_elections.php');
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Import Elections</h1>
<p><strong>CSV Format:</strong> election_name; election_date (optional, YYYY-MM-DD)</p>
<form method="post" enctype="multipart/form-data">
    <?php echo \App\Inc\csrf_input(); ?>
    <label>
        CSV File:
        <input type="file" name="csv_file" accept=".csv" required>
    </label><br>
    <button type="submit">Import</button>
</form>
<a href="/admin/imports.php">Back to Imports</a>
<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
