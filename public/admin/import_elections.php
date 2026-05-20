<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Wahl anlegen';
\App\Inc\require_admin();
$pdo = \App\Inc\db();

$stats = ['created' => 0, 'errors' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    
    try {
        $name = trim((string) ($_POST['election_name'] ?? ''));
        $date = trim((string) ($_POST['election_date'] ?? ''));

        if ($name === '') {
            throw new \RuntimeException('Bitte einen Wahlnamen eingeben.');
        }

        $dateVal = null;
        if ($date !== '') {
            $dateIso = \App\Inc\parse_date_input_to_iso($date);
            if ($dateIso === null) {
                throw new \RuntimeException('Ungültiges Datumsformat. Bitte TT.MM.JJJJ oder YYYY-MM-DD verwenden.');
            }
            $dateVal = $dateIso;
        }

        $stmt = $pdo->prepare('SELECT id FROM elections WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        if ($stmt->fetchColumn()) {
            throw new \RuntimeException('Diese Wahl existiert bereits.');
        }

        $ins = $pdo->prepare('INSERT INTO elections (name, start_date) VALUES (?, ?)');
        $ins->execute([$name, $dateVal]);
        $stats['created']++;

        $summary = "Wahl angelegt: {$name}";
        \App\Inc\flash_set('success', $summary);
        
        \App\Inc\redirect('/admin/import_elections.php');

    } catch (\Throwable $e) {
        $stats['errors']++;
        \App\Inc\flash_set('error', 'Anlegen fehlgeschlagen: ' . $e->getMessage());
        \App\Inc\redirect('/admin/import_elections.php');
    }
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Wahl anlegen</h1>
<p>Wahlen werden hier direkt im Formular angelegt, nicht per CSV.</p>
<form method="post">
    <?php echo \App\Inc\csrf_input(); ?>
    <label>
        Wahlname
        <input type="text" name="election_name" required>
    </label><br>
    <label>
        Datum (optional, TT.MM.JJJJ)
        <input type="text" name="election_date" placeholder="20.09.2026">
    </label><br>
    <button type="submit">Anlegen</button>
</form>
<a href="/admin/imports.php">Zurück zum Import-Dashboard</a>
<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
