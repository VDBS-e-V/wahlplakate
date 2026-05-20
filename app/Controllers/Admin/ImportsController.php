<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../inc/auth.php';
require_once __DIR__ . '/../../inc/util.php';

use App\Controllers\BaseController;
use App\Core\Database;

class ImportsController extends BaseController
{
	public function handle(): void
	{
		\App\Inc\require_admin();
		$pdo = Database::pdo();
		$elections = $pdo->query('SELECT id, name FROM wpl_elections ORDER BY id DESC')->fetchAll();
		$selectedElectionId = (int) ($_GET['election_id'] ?? ($elections[0]['id'] ?? 0));

		$status = [
			'parteilos' => false,
			'uploads' => is_dir(\App\Inc\env('UPLOAD_DIR', __DIR__ . '/../../../storage/uploads')),
			'counts' => [],
		];

		try {
			$status['parteilos'] = (bool) $pdo->query("SELECT id FROM wpl_parties WHERE code = 'PARTEILOS' LIMIT 1")->fetchColumn();
			$tables = [
				'parties' => 'wpl_parties',
				'election_parties' => 'wpl_election_parties',
				'election_candidates' => 'wpl_election_candidates',
				'districts' => 'wpl_districts',
				'localities' => 'wpl_localities',
				'elections' => 'wpl_elections',
			];
			foreach ($tables as $key => $table) {
				$status['counts'][$key] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
			}
		} catch (\Throwable $e) {
			$status['counts'] = [];
		}

		$this->view('admin/imports', [
			'elections' => $elections,
			'selectedElectionId' => $selectedElectionId,
			'status' => $status,
		]);
	}
}
