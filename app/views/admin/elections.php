<?php require_once __DIR__ . '/../../views/header.php'; ?>
<h1>Wahlen verwalten</h1>
<form method="post">
	<?php echo \App\Inc\csrf_input(); ?>
	<input type="hidden" name="action" value="create">
	<label>Name <input type="text" name="name" required></label>
	<label>Bundesland-Code <input type="text" name="state_code" placeholder="BW"></label>
	<label>Datum <input type="text" name="start_date" placeholder="20.09.2026"></label>
	<label><input type="checkbox" name="active" value="1" checked> Aktiv</label>
	<button type="submit">Speichern</button>
</form>
<table border="1" cellpadding="6" cellspacing="0">
	<tr><th>ID</th><th>Name</th><th>Bundesland</th><th>Datum</th><th>Aktiv</th><th>Aktionen</th></tr>
	<?php foreach ($elections as $election): ?>
		<tr>
			<td><?php echo (int) $election['id']; ?></td>
			<td><?php echo \App\Inc\h($election['name']); ?></td>
			<td><?php echo \App\Inc\h((string) ($election['state_code'] ?? '')); ?></td>
			<td><?php echo \App\Inc\h(\App\Inc\format_date_de($election['start_date'])); ?></td>
			<td><?php echo (int) $election['active'] === 1 ? 'Ja' : 'Nein'; ?></td>
			<td>
				<form method="post" style="display:inline-block;">
					<?php echo \App\Inc\csrf_input(); ?>
					<input type="hidden" name="action" value="toggle_active">
					<input type="hidden" name="election_id" value="<?php echo (int) $election['id']; ?>">
					<input type="hidden" name="active" value="<?php echo (int) $election['active'] ? 0 : 1; ?>">
					<button type="submit"><?php echo (int) $election['active'] ? 'Deaktivieren' : 'Aktivieren'; ?></button>
				</form>
				<form method="post" style="display:inline-block;" onsubmit="return confirm('Wahl wirklich löschen?');">
					<?php echo \App\Inc\csrf_input(); ?>
					<input type="hidden" name="action" value="delete">
					<input type="hidden" name="election_id" value="<?php echo (int) $election['id']; ?>">
					<button type="submit">Löschen</button>
				</form>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
<?php require_once __DIR__ . '/../../views/footer.php'; ?>
