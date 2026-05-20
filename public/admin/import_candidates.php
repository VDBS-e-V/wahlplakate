<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/csv.php';
require_once __DIR__ . '/../../app/inc/util.php';
require_once __DIR__ . '/../../app/inc/idgen.php';

$pageTitle = 'Kandidat*innen importieren';
\App\Inc\require_admin();
$pdo = \App\Inc\db();
$selectedElectionId = \App\Inc\require_election_selected();

$elections = $pdo->query('SELECT id, name FROM elections ORDER BY id DESC')->fetchAll();
$stats = ['created' => 0, 'skipped' => 0, 'errors' => 0];
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
                $name = trim((string) ($row['candidate_name'] ?? ''));
                $partyCode = trim((string) ($row['party_code'] ?? ''));

                if ($name === '') {
                    $stats['errors']++;
                    $errors[] = 'candidate_name fehlt.';
                    continue;
                }

                if ($partyCode === '') {
                    $partyCode = 'PARTEILOS';
                }

                $partyStmt = $pdo->prepare('SELECT ep.id FROM election_parties ep INNER JOIN parties p ON p.id = ep.party_id WHERE ep.election_id = ? AND p.code = ? LIMIT 1');
                $partyStmt->execute([$selectedElectionId, $partyCode]);
                $electionPartyId = $partyStmt->fetchColumn();

                if (! $electionPartyId) {
                    $stats['errors']++;
                    $errors[] = 'Partei ' . $partyCode . ' ist dieser Wahl nicht zugeordnet.';
                    continue;
                }

                $checkStmt = $pdo->prepare('SELECT 1 FROM election_candidates WHERE election_id = ? AND election_party_id = ? AND name = ? LIMIT 1');
                $checkStmt->execute([$selectedElectionId, $electionPartyId, $name]);
                if ($checkStmt->fetchColumn()) {
                    $stats['skipped']++;
                    continue;
                }

                $candidateCode = \App\Inc\generate_candidate_code($pdo, $partyCode, $name);
                $ins = $pdo->prepare('INSERT INTO election_candidates (election_id, election_party_id, name, candidate_code) VALUES (?, ?, ?, ?)');
                $ins->execute([$selectedElectionId, $electionPartyId, $name, $candidateCode]);
                $stats['created']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $errors[] = $e->getMessage();
            }
        }

        $summary = "Import abgeschlossen: {$stats['created']} neu, {$stats['skipped']} übersprungen, {$stats['errors']} Fehler";
        \App\Inc\flash_set('success', $summary);
        \App\Inc\redirect('/admin/import_candidates.php?election_id=' . $selectedElectionId);
    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Import fehlgeschlagen: ' . $e->getMessage());
        \App\Inc\redirect('/admin/import_candidates.php?election_id=' . $selectedElectionId);
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Kandidat*innen importieren</h1>
<p><strong>CSV-Format:</strong> candidate_name; party_code (optional, Standard PARTEILOS)</p>
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
        CSV-Datei:
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
