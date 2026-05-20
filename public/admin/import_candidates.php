<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/csv.php';
require_once __DIR__ . '/../../app/inc/util.php';
require_once __DIR__ . '/../../app/inc/idgen.php';

$pageTitle = 'Import Candidates';
\App\Inc\require_admin();
$pdo = \App\Inc\db();

$stats = ['created' => 0, 'skipped' => 0, 'errors' => 0, 'messages' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    
    try {
        $rows = \App\Inc\read_csv_uploaded('csv_file');

        if (empty($rows)) {
            throw new \RuntimeException('CSV file is empty');
        }

        foreach ($rows as $row) {
            try {
                $name = trim((string) ($row['candidate_name'] ?? ''));
                $party_code = trim((string) ($row['party_code'] ?? ''));

                if ($name === '') {
                    $stats['errors']++;
                    continue;
                }

                if ($party_code === '') {
                    $party_code = 'PARTEILOS';
                }

                $party_stmt = $pdo->prepare('SELECT id FROM parties WHERE code = ? LIMIT 1');
                $party_stmt->execute([$party_code]);
                $party_id = $party_stmt->fetchColumn();

                if (!$party_id) {
                    $stats['errors']++;
                    continue;
                }

                $check_stmt = $pdo->prepare('SELECT 1 FROM candidates WHERE name = ? AND party_id = ? LIMIT 1');
                $check_stmt->execute([$name, $party_id]);

                if ($check_stmt->fetchColumn()) {
                    $stats['skipped']++;
                    continue;
                }

                $candidate_code = \App\Inc\generate_candidate_code($pdo, $party_code, $name);

                $ins = $pdo->prepare('INSERT INTO candidates (party_id, name, candidate_code) VALUES (?, ?, ?)');
                $ins->execute([$party_id, $name, $candidate_code]);
                $stats['created']++;

            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }
        
        $summary = "Import complete: {$stats['created']} created, {$stats['skipped']} skipped, {$stats['errors']} errors";
        \App\Inc\flash_set('success', $summary);
        \App\Inc\redirect('/admin/import_candidates.php');
        
    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Import failed: ' . $e->getMessage());
        \App\Inc\redirect('/admin/import_candidates.php');
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Import Candidates</h1>
<p><strong>CSV Format:</strong> candidate_name; party_code (optional, defaults to PARTEILOS)</p>
<p><strong>Behavior:</strong> Append-only. Candidates with matching (name, party_id) are skipped. Candidate codes are auto-generated as: partyCode-nameCode-randomBase36</p>
<p><strong>Note:</strong> Party must exist in database before importing candidates.</p>
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
