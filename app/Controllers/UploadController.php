<?php
namespace App\Controllers;

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/image_store.php';
require_once __DIR__ . '/../inc/util.php';

use App\Core\Database;

class UploadController extends BaseController
{
    public function handle(): void
    {
        // reuse the existing procedural logic but move into controller
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        \App\Inc\require_login();
        $pdo = Database::pdo();

        $defaultElectionId = \App\Inc\get_default_election_id();
        $changeElection = isset($_GET['change_election']) && $_GET['change_election'];
        $step = (int) ($_GET['step'] ?? $_POST['step'] ?? ($defaultElectionId && ! $changeElection ? 2 : 1));

        $elections = $pdo->query('SELECT id, name FROM wpl_elections ORDER BY id DESC')->fetchAll();

        if (! isset($_SESSION['upload_flow']) || $changeElection) {
            $_SESSION['upload_flow'] = [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            \App\Inc\csrf_verify_or_die();
            try {
                switch ($step) {
                    case 1:
                        $sel = (int) ($_POST['election_id'] ?? 0);
                        if ($sel <= 0) {
                            throw new \RuntimeException('Bitte eine Wahl auswählen.');
                        }
                        $stmt = $pdo->prepare('SELECT id FROM wpl_elections WHERE id = ? LIMIT 1');
                        $stmt->execute([$sel]);
                        if (! $stmt->fetchColumn()) {
                            throw new \RuntimeException('Ungültige Wahl.');
                        }
                        $_SESSION['upload_flow']['election_id'] = $sel;
                        if (! empty($_POST['save_default'])) {
                            \App\Inc\set_default_election_id($sel);
                        }
                        \App\Inc\redirect('upload.php?step=2');
                        break;

                    case 2:
                        $electionId = $_SESSION['upload_flow']['election_id'] ?? $defaultElectionId;
                        $districtId = (int) ($_POST['district_id'] ?? 0);
                        if ($districtId <= 0) {
                            throw new \RuntimeException('Bitte einen Bezirk auswählen.');
                        }
                        $stmt = $pdo->prepare('SELECT id FROM wpl_districts WHERE id = ? AND election_id = ? LIMIT 1');
                        $stmt->execute([$districtId, $electionId]);
                        if (! $stmt->fetchColumn()) {
                            throw new \RuntimeException('Der ausgewählte Bezirk gehört nicht zur Wahl.');
                        }
                        $_SESSION['upload_flow']['district_id'] = $districtId;
                        \App\Inc\redirect('upload.php?step=3');
                        break;

                    case 3:
                        $districtId = $_SESSION['upload_flow']['district_id'] ?? 0;
                        $localityId = (int) ($_POST['locality_id'] ?? 0);
                        if ($localityId <= 0) {
                            throw new \RuntimeException('Bitte einen Ortsteil auswählen.');
                        }
                        $stmt = $pdo->prepare('SELECT l.id FROM wpl_localities l INNER JOIN wpl_districts d ON d.id = l.district_id WHERE l.id = ? AND d.id = ? LIMIT 1');
                        $stmt->execute([$localityId, $districtId]);
                        if (! $stmt->fetchColumn()) {
                            throw new \RuntimeException('Ortsteil gehört nicht zum Bezirk.');
                        }
                        $_SESSION['upload_flow']['locality_id'] = $localityId;
                        \App\Inc\redirect('upload.php?step=4');
                        break;

                    case 4:
                        $electionId = $_SESSION['upload_flow']['election_id'] ?? $defaultElectionId;
                        $epId = isset($_POST['election_party_id']) && $_POST['election_party_id'] !== '' ? (int) $_POST['election_party_id'] : 0;

                        // Server-side fallback: try to resolve typed party label/code to an id
                        if ($epId <= 0) {
                            $partyInput = trim((string) ($_POST['election_party_input'] ?? ''));
                            if ($partyInput !== '') {
                                // exact matches: ballot_label/name, code, or 'label / code'
                                $stmt = $pdo->prepare(
                                    'SELECT ep.id FROM wpl_election_parties ep INNER JOIN wpl_parties p ON p.id = ep.party_id WHERE ep.election_id = ? AND (' .
                                    'LOWER(COALESCE(ep.ballot_label, p.name)) = LOWER(?) OR LOWER(p.code) = LOWER(?) OR LOWER(CONCAT(COALESCE(ep.ballot_label, p.name), " / ", p.code)) = LOWER(?)' .
                                    ') LIMIT 1'
                                );
                                $stmt->execute([$electionId, $partyInput, $partyInput, $partyInput]);
                                $found = $stmt->fetchColumn();
                                if ($found) {
                                    $epId = (int) $found;
                                }
                            }
                        }

                        if ($epId <= 0) {
                            // try fuzzy/partial match when exact not found
                            $partyInput = trim((string) ($_POST['election_party_input'] ?? ''));
                            if ($partyInput !== '') {
                                $like = '%' . $partyInput . '%';
                                $stmt = $pdo->prepare(
                                    'SELECT ep.id FROM wpl_election_parties ep INNER JOIN wpl_parties p ON p.id = ep.party_id WHERE ep.election_id = ? AND (' .
                                    'LOWER(COALESCE(ep.ballot_label, p.name)) LIKE LOWER(?) OR LOWER(p.code) LIKE LOWER(?) OR LOWER(CONCAT(COALESCE(ep.ballot_label,p.name), " / ", p.code)) LIKE LOWER(?)' .
                                    ') ORDER BY ep.id LIMIT 1'
                                );
                                $stmt->execute([$electionId, $like, $like, $like]);
                                $found = $stmt->fetchColumn();
                                if ($found) {
                                    $epId = (int) $found;
                                }
                            }
                        }

                        if ($epId <= 0) {
                            throw new \RuntimeException('Bitte eine Partei auswählen.');
                        }

                        $stmt = $pdo->prepare('SELECT id FROM wpl_election_parties WHERE id = ? AND election_id = ? LIMIT 1');
                        $stmt->execute([$epId, $electionId]);
                        if (! $stmt->fetchColumn()) {
                            throw new \RuntimeException('Die ausgewählte Partei gehört nicht zur Wahl.');
                        }

                        $_SESSION['upload_flow']['election_party_id'] = $epId;
                        \App\Inc\redirect('upload.php?step=5');
                        break;

                    case 5:
                        $electionId = $_SESSION['upload_flow']['election_id'] ?? $defaultElectionId;
                        $candId = isset($_POST['election_candidate_id']) && $_POST['election_candidate_id'] !== '' ? (int) $_POST['election_candidate_id'] : null;

                        if ($candId === null) {
                            $candInput = trim((string) ($_POST['election_candidate_input'] ?? ''));
                            if ($candInput !== '') {
                                // try exact name match
                                $stmt = $pdo->prepare('SELECT id, election_party_id FROM wpl_election_candidates WHERE election_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
                                $stmt->execute([$electionId, $candInput]);
                                $candidate = $stmt->fetch();
                                if (! $candidate) {
                                    // try full display value match: "Name (Party / CODE)"
                                    $stmt = $pdo->prepare('SELECT ec.id, ec.election_party_id FROM wpl_election_candidates ec INNER JOIN wpl_election_parties ep ON ep.id = ec.election_party_id INNER JOIN wpl_parties p ON p.id = ep.party_id WHERE ec.election_id = ? AND LOWER(CONCAT(ec.name, " (", COALESCE(ep.ballot_label,p.name), " / ", p.code, ")")) = LOWER(?) LIMIT 1');
                                    $stmt->execute([$electionId, $candInput]);
                                    $candidate = $stmt->fetch();
                                }
                                if (! $candidate) {
                                    // try partial match
                                    $like = '%' . $candInput . '%';
                                    $stmt = $pdo->prepare('SELECT ec.id, ec.election_party_id FROM wpl_election_candidates ec INNER JOIN wpl_election_parties ep ON ep.id = ec.election_party_id INNER JOIN wpl_parties p ON p.id = ep.party_id WHERE ec.election_id = ? AND (LOWER(ec.name) LIKE LOWER(?) OR LOWER(CONCAT(ec.name, " (", COALESCE(ep.ballot_label,p.name), " / ", p.code, ")")) LIKE LOWER(?)) ORDER BY ec.id LIMIT 1');
                                    $stmt->execute([$electionId, $like, $like]);
                                    $candidate = $stmt->fetch();
                                }
                                if ($candidate) {
                                    $candId = (int) $candidate['id'];
                                }
                            }
                        } else {
                            $stmt = $pdo->prepare('SELECT id, election_party_id FROM wpl_election_candidates WHERE id = ? AND election_id = ? LIMIT 1');
                            $stmt->execute([$candId, $electionId]);
                            $candidate = $stmt->fetch();
                            if (! $candidate) {
                                throw new \RuntimeException('Kandidat*in nicht gefunden.');
                            }
                        }

                        if ($candId !== null) {
                            // ensure candidate belongs to selected party (if party set)
                            $stmt = $pdo->prepare('SELECT election_party_id FROM wpl_election_candidates WHERE id = ? LIMIT 1');
                            $stmt->execute([$candId]);
                            $candidateRow = $stmt->fetch();
                            if (! $candidateRow) {
                                throw new \RuntimeException('Kandidat*in nicht gefunden.');
                            }
                            if (! empty($_SESSION['upload_flow']['election_party_id']) && (int)$candidateRow['election_party_id'] !== (int) $_SESSION['upload_flow']['election_party_id']) {
                                throw new \RuntimeException('Die gewählte Partei passt nicht zur Kandidat*in.');
                            }
                        }

                        $_SESSION['upload_flow']['election_candidate_id'] = $candId;
                        \App\Inc\redirect('upload.php?step=6');
                        break;

                    case 6:
                        $flow = $_SESSION['upload_flow'] ?? [];
                        $electionId = $flow['election_id'] ?? $defaultElectionId;
                        $electionPartyId = $flow['election_party_id'] ?? null;
                        $localityId = $flow['locality_id'] ?? null;
                        $electionCandidateId = $flow['election_candidate_id'] ?? null;

                        if (! $electionId || ! $electionPartyId || ! $localityId) {
                            throw new \RuntimeException('Unvollständige Auswahl. Bitte den Ablauf erneut durchlaufen.');
                        }

                        if (! isset($_FILES['image'])) {
                            throw new \RuntimeException('Bitte eine Datei auswählen.');
                        }

                        $validated = \App\Inc\validate_upload($_FILES['image']);

                        $dup = $pdo->prepare('SELECT id FROM wpl_images WHERE sha256 = ? LIMIT 1');
                        $dup->execute([$validated['sha256']]);
                        $existing = $dup->fetchColumn();
                        if ($existing) {
                            throw new \RuntimeException('Ein Bild mit derselben Signatur existiert bereits (ID ' . $existing . ').');
                        }

                        // build descriptive labels for filename
                        $electionName = $pdo->prepare('SELECT name FROM wpl_elections WHERE id = ? LIMIT 1');
                        $electionName->execute([$electionId]);
                        $electionName = $electionName->fetchColumn() ?: '';

                        $localityName = $pdo->prepare('SELECT name FROM wpl_localities WHERE id = ? LIMIT 1');
                        $localityName->execute([$localityId]);
                        $localityName = $localityName->fetchColumn() ?: '';

                        $partyName = '';
                        $partyStmt = $pdo->prepare('SELECT ep.ballot_label, p.name as party_name FROM wpl_election_parties ep LEFT JOIN wpl_parties p ON ep.party_id = p.id WHERE ep.id = ? LIMIT 1');
                        $partyStmt->execute([$electionPartyId]);
                        $partyRow = $partyStmt->fetch();
                        if ($partyRow) {
                            $partyName = $partyRow['ballot_label'] ?? $partyRow['party_name'] ?? '';
                        }

                        $candidateName = '';
                        if ($electionCandidateId) {
                            $candStmt = $pdo->prepare('SELECT name FROM wpl_election_candidates WHERE id = ? LIMIT 1');
                            $candStmt->execute([$electionCandidateId]);
                            $candidateName = $candStmt->fetchColumn() ?: '';
                        }

                        $meta = [
                            'election' => $electionName,
                            'locality' => $localityName,
                            'party' => $partyName,
                            'candidate' => $candidateName,
                        ];

                        $stored = \App\Inc\store_upload($validated, $meta);
                        $ins = $pdo->prepare('INSERT INTO wpl_images (election_id, election_party_id, election_candidate_id, locality_id, file_path, original_filename, mime, size_bytes, sha256, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $ins->execute([$electionId, $electionPartyId, $electionCandidateId, $localityId, $stored['file_path'], $_FILES['image']['name'], $validated['mime'], $validated['size_bytes'], $validated['sha256'], $_SESSION['user_id'] ?? null]);
                        $id = $pdo->lastInsertId();

                        // clear flow selections (keep default election if set)
                        $_SESSION['upload_flow'] = [];

                        \App\Inc\flash_set('success', 'Bild erfolgreich hochgeladen. ID: ' . $id);
                        \App\Inc\redirect('image_view.php?id=' . $id);
                        break;
                }
            } catch (\Throwable $e) {
                \App\Inc\flash_set('error', 'Upload-Fehler: ' . $e->getMessage());
                \App\Inc\redirect('upload.php?step=' . $step);
            }
        }

        // render view
        $flow = $_SESSION['upload_flow'] ?? [];
        $electionId = $flow['election_id'] ?? $defaultElectionId;

        $this->view('upload', [
            'elections' => $elections,
            'step' => $step,
            'flow' => $flow,
            'electionId' => $electionId,
        ]);
    }
}
