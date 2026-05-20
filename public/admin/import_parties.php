<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/csv.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Import Parties';
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
                $name = $row['party_name'] ?? '';
                $code = $row['party_code'] ?? '';
                $short = $row['party_short'] ?? '';
                
                if (empty($code) || empty($name)) {
                    $stats['errors']++;
                    $stats['messages'][] = 'Skipped: missing party_code or party_name';
                    continue;
                }
                
                // Upsert: check if exists
                $stmt = $pdo->prepare('SELECT id FROM parties WHERE code = ? LIMIT 1');
                $stmt->execute([$code]);
                $existing = $stmt->fetchColumn();
                
                if ($existing) {
                    // Update
                    $upd = $pdo->prepare('UPDATE parties SET name = ? WHERE code = ?');
                    $upd->execute([$name, $code]);
                    $stats['updated']++;
                } else {
                    // Insert
                    $ins = $pdo->prepare('INSERT INTO parties (name, code) VALUES (?, ?)');
                    $ins->execute([$name, $code]);
                    $stats['created']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['messages'][] = 'Row error: ' . $e->getMessage();
            }
        }
        
        $summary = "Import complete: {$stats['created']} created, {$stats['updated']} updated, {$stats['errors']} errors";
        \App\Inc\flash_set('success', $summary);
        \App\Inc\redirect('import_parties.php');
        
    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Import failed: ' . $e->getMessage());
        \App\Inc\redirect('import_parties.php');
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Import Parties</h1>
<p><strong>CSV Format:</strong> party_name; party_code; party_short (optional)</p>
<p><strong>Note:</strong> PARTEILOS party must be created via CSV or manually added to enable independent candidates.</p>
<form method="post" enctype="multipart/form-data">
    <?php echo \App\Inc\csrf_input(); ?>
    <label>
        CSV File:
        <input type="file" name="csv_file" accept=".csv" required>
    </label><br>
    <button type="submit">Import</button>
</form>
<a href="imports.php">Back to Imports</a>
<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
