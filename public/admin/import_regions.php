<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/csv.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Import Regions';
\App\Inc\require_admin();
$pdo = \App\Inc\db();

$stats = ['created_districts' => 0, 'created_localities' => 0, 'updated_localities' => 0, 'errors' => 0, 'messages' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    
    try {
        $rows = \App\Inc\read_csv_uploaded('csv_file');
        
        if (empty($rows)) {
            throw new \RuntimeException('CSV file is empty');
        }
        
        foreach ($rows as $row) {
            try {
                $bezirk = $row['bezirk'] ?? '';
                $ortsteil = $row['ortsteil'] ?? '';
                
                if (empty($bezirk) || empty($ortsteil)) {
                    $stats['errors']++;
                    $stats['messages'][] = 'Skipped: missing bezirk or ortsteil';
                    continue;
                }
                
                // Upsert district
                $dist_stmt = $pdo->prepare('SELECT id FROM districts WHERE name = ? LIMIT 1');
                $dist_stmt->execute([$bezirk]);
                $district_id = $dist_stmt->fetchColumn();
                
                if (!$district_id) {
                    $ins_dist = $pdo->prepare('INSERT INTO districts (name) VALUES (?)');
                    $ins_dist->execute([$bezirk]);
                    $district_id = $pdo->lastInsertId();
                    $stats['created_districts']++;
                }
                
                // Upsert locality
                $loc_stmt = $pdo->prepare('SELECT id FROM localities WHERE district_id = ? AND name = ? LIMIT 1');
                $loc_stmt->execute([$district_id, $ortsteil]);
                $locality_id = $loc_stmt->fetchColumn();
                
                if ($locality_id) {
                    // Update
                    $upd_loc = $pdo->prepare('UPDATE localities SET district_id = ? WHERE id = ?');
                    $upd_loc->execute([$district_id, $locality_id]);
                    $stats['updated_localities']++;
                } else {
                    // Insert
                    $ins_loc = $pdo->prepare('INSERT INTO localities (district_id, name) VALUES (?, ?)');
                    $ins_loc->execute([$district_id, $ortsteil]);
                    $stats['created_localities']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['messages'][] = 'Row error: ' . $e->getMessage();
            }
        }
        
        $summary = "Import complete: {$stats['created_districts']} districts, {$stats['created_localities']} localities created, {$stats['updated_localities']} localities updated, {$stats['errors']} errors";
        \App\Inc\flash_set('success', $summary);
        \App\Inc\redirect('import_regions.php');
        
    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Import failed: ' . $e->getMessage());
        \App\Inc\redirect('import_regions.php');
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Import Regions</h1>
<p><strong>CSV Format:</strong> bezirk; ortsteil</p>
<p>Bezirk (district) is created if not exists; Ortsteil (locality) is upseated per district.</p>
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
