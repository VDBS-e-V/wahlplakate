<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/csv.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Parteien für Wahl importieren';
\App\Inc\require_admin();
$pdo = \App\Inc\db();
$selectedElectionId = \App\Inc\require_election_selected();

$elections = $pdo->query('SELECT id, name FROM wpl_elections ORDER BY id DESC')->fetchAll();

$stats = ['inserted' => 0, 'updated' => 0, 'linked' => 0, 'errors' => 0];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    $ensureParteilos = isset($_POST['ensure_parteilos']) && $_POST['ensure_parteilos'] !== '0';
    $selectedElectionId = \App\Inc\require_election_selected($selectedElectionId);

    try {
        $rows = \App\Inc\read_csv_uploaded('csv_file');

        if (empty($rows)) {
            throw new \RuntimeException('Die CSV-Datei ist leer.');
        }

        foreach ($rows as $row) {
            try {
                $name = trim((string) ($row['party_name'] ?? ''));
                $code = trim((string) ($row['party_code'] ?? ''));
                $ballotLabel = trim((string) ($row['ballot_label'] ?? ''));

                if ($name === '' || $code === '') {
                    $stats['errors']++;
                    $errors[] = 'party_name oder party_code fehlt.';
                    continue;
                }

                $stmt = $pdo->prepare('SELECT id FROM wpl_parties WHERE code = ? LIMIT 1');
                $stmt->execute([$code]);
                $existing = $stmt->fetchColumn();

                if ($existing) {
                    $upd = $pdo->prepare('UPDATE wpl_parties SET name = ? WHERE code = ?');
                    $upd->execute([$name, $code]);
                    $stats['updated']++;
                    $partyId = (int) $existing;
                } else {
                    $ins = $pdo->prepare('INSERT INTO wpl_parties (name, code) VALUES (?, ?)');
                    $ins->execute([$name, $code]);
                    $stats['inserted']++;
                    $partyId = (int) $pdo->lastInsertId();
                }

                if ($ballotLabel === '') {
                    $ballotLabel = $name;
                }

                $link = $pdo->prepare('SELECT id FROM wpl_election_parties WHERE election_id = ? AND party_id = ? LIMIT 1');
                $link->execute([$selectedElectionId, $partyId]);
                $electionPartyId = $link->fetchColumn();

                if ($electionPartyId) {
                    $updLink = $pdo->prepare('UPDATE wpl_election_parties SET ballot_label = ? WHERE id = ?');
                    $updLink->execute([$ballotLabel, $electionPartyId]);
                } else {
                    $insLink = $pdo->prepare('INSERT INTO wpl_election_parties (election_id, party_id, ballot_label) VALUES (?, ?, ?)');
                    $insLink->execute([$selectedElectionId, $partyId, $ballotLabel]);
                    $stats['linked']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $errors[] = $e->getMessage();
            }
        }

        if ($ensureParteilos) {
            $stmt = $pdo->prepare('SELECT id FROM wpl_parties WHERE code = ? LIMIT 1');
            $stmt->execute(['PARTEILOS']);
            $partyId = $stmt->fetchColumn();
            if (! $partyId) {
                $ins = $pdo->prepare('INSERT INTO wpl_parties (name, code) VALUES (?, ?)');
                $ins->execute(['Parteilos', 'PARTEILOS']);
                $partyId = (int) $pdo->lastInsertId();
                $stats['inserted']++;
            }
            $link = $pdo->prepare('SELECT id FROM wpl_election_parties WHERE election_id = ? AND party_id = ? LIMIT 1');
            $link->execute([$selectedElectionId, $partyId]);
            if (! $link->fetchColumn()) {
                $insLink = $pdo->prepare('INSERT INTO wpl_election_parties (election_id, party_id, ballot_label) VALUES (?, ?, ?)');
                $insLink->execute([$selectedElectionId, $partyId, 'Parteilos']);
                $stats['linked']++;
            }
        }

        $summary = "Import abgeschlossen: {$stats['inserted']} neu, {$stats['updated']} aktualisiert, {$stats['linked']} verknüpft, {$stats['errors']} Fehler";
        \App\Inc\flash_set('success', $summary);
        \App\Inc\redirect('/admin/import_parties.php?election_id=' . $selectedElectionId);

    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Import fehlgeschlagen: ' . $e->getMessage());
        \App\Inc\redirect('/admin/import_parties.php?election_id=' . $selectedElectionId);
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Parteien für Wahl importieren</h1>
<p><strong>CSV-Format:</strong> party_code; party_name; ballot_label (optional)</p>
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
    <label>
        <input type="checkbox" name="ensure_parteilos" value="1" checked>
        PARTEILOS automatisch anlegen
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
