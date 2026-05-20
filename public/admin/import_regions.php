<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/csv.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Ortsteile importieren';
\App\Inc\require_admin();
$pdo = \App\Inc\db();
$selectedElectionId = \App\Inc\require_election_selected();

$elections = $pdo->query('SELECT id, name FROM wpl_elections ORDER BY id DESC')->fetchAll();

$stats = ['created_districts' => 0, 'created_localities' => 0, 'updated_localities' => 0, 'errors' => 0];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    $selectedElectionId = \App\Inc\require_election_selected($selectedElectionId);
    
    try {
        $rows = \App\Inc\read_csv_uploaded('csv_file');

        if (empty($rows)) {
            throw new \RuntimeException('Die CSV-Datei ist leer.');
        }

        foreach ($rows as $row) {
            try {
                $bezirk = trim((string) ($row['bezirk'] ?? ''));
                $ortsteil = trim((string) ($row['ortsteil'] ?? ''));

                if ($bezirk === '' || $ortsteil === '') {
                    $stats['errors']++;
                    $errors[] = 'bezirk oder ortsteil fehlt.';
                    continue;
                }

                $dist_stmt = $pdo->prepare('SELECT id FROM wpl_districts WHERE election_id = ? AND name = ? LIMIT 1');
                $dist_stmt->execute([$selectedElectionId, $bezirk]);
                $district_id = $dist_stmt->fetchColumn();

                if (!$district_id) {
                    $ins_dist = $pdo->prepare('INSERT INTO wpl_districts (election_id, name) VALUES (?, ?)');
                    $ins_dist->execute([$selectedElectionId, $bezirk]);
                    $district_id = $pdo->lastInsertId();
                    $stats['created_districts']++;
                }

                $loc_stmt = $pdo->prepare('SELECT id FROM wpl_localities WHERE district_id = ? AND name = ? LIMIT 1');
                $loc_stmt->execute([$district_id, $ortsteil]);
                $locality_id = $loc_stmt->fetchColumn();

                if ($locality_id) {
                    $upd_loc = $pdo->prepare('UPDATE wpl_localities SET district_id = ? WHERE id = ?');
                    $upd_loc->execute([$district_id, $locality_id]);
                    $stats['updated_localities']++;
                } else {
                    $ins_loc = $pdo->prepare('INSERT INTO wpl_localities (district_id, name) VALUES (?, ?)');
                    $ins_loc->execute([$district_id, $ortsteil]);
                    $stats['created_localities']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }
        
        $summary = "Import abgeschlossen: {$stats['created_districts']} Bezirke, {$stats['created_localities']} Ortsteile neu, {$stats['updated_localities']} Ortsteile aktualisiert, {$stats['errors']} Fehler";
        \App\Inc\flash_set('success', $summary);
        \App\Inc\redirect('/admin/import_regions.php?election_id=' . $selectedElectionId);
        
    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Import fehlgeschlagen: ' . $e->getMessage());
        \App\Inc\redirect('/admin/import_regions.php?election_id=' . $selectedElectionId);
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Ortsteile importieren</h1>
<p><strong>CSV-Format:</strong> bezirk; ortsteil</p>
<form method="post" enctype="multipart/form-data">
    <?php echo \App\Inc\csrf_input(); ?>
    <input type="hidden" name="election_id" value="<?php echo (int) $selectedElectionId; ?>">
	<label>
		Wahl auswählen
		<select name="election_id" required>
			<option value="">-- bitte wählen --</option>
			<?php foreach ($elections as $election): ?>
				<option value="<?php echo (int) $election['id']; ?>" <?php echo (int) $election['id'] === $selectedElectionId ? 'selected' : ''; ?>><?php echo \App\Inc\h($election['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</label><br>
    <label>
        CSV-Datei
        <input type="file" name="csv_file" accept=".csv" required>
    </label><br>
    <button type="submit">Importieren</button>
</form>
<?php if ($errors !== []): ?>
	<h2>Erste Fehler</h2>
	<table border="1" cellpadding="6" cellspacing="0">
		<tr><th>#</th><th>Nachricht</th></tr>
		<?php foreach (array_slice($errors, 0, 10) as $index => $message): ?>
			<tr><td><?php echo $index + 1; ?></td><td><?php echo \App\Inc\h($message); ?></td></tr>
		<?php endforeach; ?>
	</table>
<?php endif; ?>
<a href="/admin/imports.php?election_id=<?php echo (int) $selectedElectionId; ?>">Zurück zum Import-Dashboard</a>
<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
