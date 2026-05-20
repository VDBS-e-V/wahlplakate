<?php require_once __DIR__ . '/../../views/header.php'; ?>
<h1>Admin Imports</h1>
<p>CSV-Headerzeile ist Pflicht. Trennzeichen: Semikolon.</p>
<form method="get" action="/admin/imports.php" style="margin:1rem 0;">
	<label>
		Wahl
		<select name="election_id" required>
			<?php foreach ($elections as $election): ?>
				<option value="<?php echo (int) $election['id']; ?>" <?php echo (int) $election['id'] === $selectedElectionId ? 'selected' : ''; ?>><?php echo \App\Inc\h($election['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<button type="submit">Wahl auswählen</button>
</form>
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
		<?php $labels = ['parties' => 'Parteien', 'election_parties' => 'Wahl-Parteien', 'election_candidates' => 'Wahl-Kandidat*innen', 'districts' => 'Bezirke', 'localities' => 'Ortsteile', 'elections' => 'Wahlen']; ?>
		<div style="background:#fafafa;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;">
			<strong><?php echo \App\Inc\h($labels[$table] ?? ucfirst($table)); ?></strong><br>
			<?php echo (int) $count; ?> Einträge
		</div>
	<?php endforeach; ?>
</div>
<h2>Importe</h2>
<ul>
	<li><a href="/admin/users.php">Benutzerverwaltung</a></li>
	<li><a href="/admin/elections.php">Wahlen verwalten</a> - anlegen, aktivieren, deaktivieren, löschen</li>
	<li><a href="/admin/import_parties.php?election_id=<?php echo (int) $selectedElectionId; ?>">Parteien für Wahl importieren</a> - party_code; party_name; ballot_label (optional)</li>
	<li><a href="/admin/import_regions.php?election_id=<?php echo (int) $selectedElectionId; ?>">Ortsteile importieren</a> - bezirk; ortsteil</li>
	<li><a href="/admin/import_candidates.php?election_id=<?php echo (int) $selectedElectionId; ?>">Kandidat*innen importieren</a> - candidate_name; party_code (optional, Standard PARTEILOS)</li>
</ul>
<h2>CSV-Vorlagen</h2>
<ul>
	<li><a href="/admin/template.php?name=election_parties&election_id=<?php echo (int) $selectedElectionId; ?>">election_parties_template.csv</a></li>
	<li><a href="/admin/template.php?name=regions&election_id=<?php echo (int) $selectedElectionId; ?>">regions_template.csv</a></li>
	<li><a href="/admin/template.php?name=candidates&election_id=<?php echo (int) $selectedElectionId; ?>">candidates_template.csv</a></li>
</ul>
<?php require_once __DIR__ . '/../../views/footer.php'; ?>
