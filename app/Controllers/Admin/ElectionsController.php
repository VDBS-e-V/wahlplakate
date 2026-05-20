<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/csrf.php';
require_once __DIR__ . '/../../inc/util.php';

use App\Controllers\BaseController;
use App\Core\Database;

class ElectionsController extends BaseController
{
	public function handle(): void
	{
		\App\Inc\require_admin();
		$pdo = Database::pdo();

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			\App\Inc\csrf_verify_or_die();
			$action = (string) ($_POST['action'] ?? 'create');
			try {
				if ($action === 'create') {
					$name = trim((string) ($_POST['name'] ?? ''));
					$stateCode = trim((string) ($_POST['state_code'] ?? ''));
					$startDate = trim((string) ($_POST['start_date'] ?? ''));
					$active = isset($_POST['active']) ? 1 : 0;
					if ($name === '') {
						throw new \RuntimeException('Bitte einen Wahlnamen eingeben.');
					}
					$startIso = $startDate !== '' ? \App\Inc\parse_date_input_to_iso($startDate) : null;
					if ($startDate !== '' && $startIso === null) {
						throw new \RuntimeException('Ungültiges Datumsformat.');
					}
					$stmt = $pdo->prepare('INSERT INTO wpl_elections (name, state_code, start_date, active) VALUES (?, ?, ?, ?)');
					$stmt->execute([$name, $stateCode !== '' ? $stateCode : null, $startIso, $active]);
					\App\Inc\flash_set('success', 'Wahl angelegt.');
				} elseif ($action === 'toggle_active') {
					$id = (int) ($_POST['election_id'] ?? 0);
					$active = (int) ($_POST['active'] ?? 0);
					$stmt = $pdo->prepare('UPDATE wpl_elections SET active = ? WHERE id = ?');
					$stmt->execute([$active ? 1 : 0, $id]);
					\App\Inc\flash_set('success', 'Status aktualisiert.');
				} elseif ($action === 'delete') {
					$id = (int) ($_POST['election_id'] ?? 0);
					$checks = [
						'SELECT COUNT(*) FROM wpl_districts WHERE election_id = ?',
						'SELECT COUNT(*) FROM wpl_election_parties WHERE election_id = ?',
						'SELECT COUNT(*) FROM wpl_election_candidates WHERE election_id = ?',
						'SELECT COUNT(*) FROM wpl_images WHERE election_id = ?',
					];
					foreach ($checks as $sql) {
						$stmt = $pdo->prepare($sql);
						$stmt->execute([$id]);
						if ((int) $stmt->fetchColumn() > 0) {
							throw new \RuntimeException('Wahl kann nicht gelöscht werden, weil noch Referenzen vorhanden sind.');
						}
					}
					$stmt = $pdo->prepare('DELETE FROM wpl_elections WHERE id = ?');
					$stmt->execute([$id]);
					\App\Inc\flash_set('success', 'Wahl gelöscht.');
				}
			} catch (\Throwable $e) {
				\App\Inc\flash_set('error', 'Aktion fehlgeschlagen: ' . $e->getMessage());
			}
			\App\Inc\redirect('/admin/elections.php');
		}

		$elections = $pdo->query('SELECT id, name, state_code, start_date, end_date, active, created_at FROM wpl_elections ORDER BY id DESC')->fetchAll();
		$this->view('admin/elections', ['elections' => $elections]);
	}
}
