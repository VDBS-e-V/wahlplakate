<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/db.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Admin Imports';
\App\Inc\require_admin();
$pdo = \App\Inc\db();

$status = [
	'parteilos' => false,
	'uploads' => is_dir(\App\Inc\env('UPLOAD_DIR', __DIR__ . '/../../storage/uploads')),
	'counts' => [],
];

try {
	$status['parteilos'] = (bool) $pdo->query("SELECT id FROM parties WHERE code = 'PARTEILOS' LIMIT 1")->fetchColumn();
	foreach (['parties', 'candidates', 'localities', 'elections'] as $table) {
		$status['counts'][$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
	}
} catch (\Throwable $e) {
	$status['counts'] = [];
}
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Admin Imports</h1>
<p>CSV header row is required. Delimiter: semicolon.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;margin:1rem 0;">
	<div style="background:#eef6ff;border:1px solid #b6d4fe;border-radius:8px;padding:1rem;">
		<strong>PARTEILOS vorhanden?</strong><br>
		<?php echo $status['parteilos'] ? 'Ja' : 'Nein'; ?>
	</div>
	<div style="background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;padding:1rem;">
		<strong>UPLOAD_DIR erreichbar?</strong><br>
		<?php echo $status['uploads'] ? 'Ja' : 'Nein'; ?>
	</div>
	<?php foreach ($status['counts'] as $table => $count): ?>
		<div style="background:#fafafa;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;">
			<strong><?php echo \App\Inc\h(ucfirst($table)); ?></strong><br>
			<?php echo (int) $count; ?> rows
		</div>
	<?php endforeach; ?>
</div>
<h2>Importe</h2>
<ul>
	<li><a href="/admin/users.php">User Management</a></li>
	<li><a href="/admin/import_parties.php">Import Parties</a> - party_name; party_code; party_short (optional)</li>
	<li><a href="/admin/import_regions.php">Import Regions</a> - bezirk; ortsteil</li>
	<li><a href="/admin/import_elections.php">Import Elections</a> - election_name; election_date (optional)</li>
	<li><a href="/admin/import_candidates.php">Import Candidates</a> - candidate_name; party_code (optional, default PARTEILOS)</li>
</ul>
<h2>CSV-Vorlagen</h2>
<ul>
	<li><a href="/admin/template.php?name=parties">parties_template.csv</a></li>
	<li><a href="/admin/template.php?name=regions">regions_template.csv</a></li>
	<li><a href="/admin/template.php?name=elections">elections_template.csv</a></li>
	<li><a href="/admin/template.php?name=candidates">candidates_template.csv</a></li>
</ul>
<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
