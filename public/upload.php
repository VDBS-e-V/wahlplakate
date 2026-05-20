<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/db.php';
require_once __DIR__ . '/../app/inc/image_store.php';
require_once __DIR__ . '/../app/inc/util.php';
require_once __DIR__ . '/../app/inc/csrf.php';

$pageTitle = 'Upload Image';
\App\Inc\require_login();
$pdo = \App\Inc\db();

// fetch dropdown data
$elections = $pdo->query('SELECT id, name FROM elections ORDER BY id DESC')->fetchAll();
$localities = $pdo->query('SELECT id, name FROM localities ORDER BY name')->fetchAll();
$parties = $pdo->query('SELECT id, name, code FROM parties ORDER BY name')->fetchAll();
$candidates = $pdo->query('SELECT id, name, party_id FROM candidates ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \App\Inc\csrf_verify_or_die();
    try {
        if (empty($_POST['election_id']) || empty($_POST['locality_id']) || empty($_POST['party_id'])) {
            throw new \RuntimeException('Missing required fields');
        }
        $election_id = (int) $_POST['election_id'];
        $locality_id = (int) $_POST['locality_id'];
        $party_id = (int) $_POST['party_id'];
        $candidate_id = isset($_POST['candidate_id']) && $_POST['candidate_id'] !== '' ? (int) $_POST['candidate_id'] : null;

        if (! isset($_FILES['image'])) {
            throw new \RuntimeException('No file uploaded');
        }
        $validated = \App\Inc\validate_upload($_FILES['image']);

        // candidate checks
        if ($candidate_id !== null) {
            $st = $pdo->prepare('SELECT id, party_id FROM candidates WHERE id = ? LIMIT 1');
            $st->execute([$candidate_id]);
            $cand = $st->fetch();
            if (! $cand) {
                throw new \RuntimeException('Candidate not found');
            }
            $cand_party = $cand['party_id'];
            if ($cand_party !== null && (int)$cand_party !== $party_id) {
                throw new \RuntimeException('Selected party does not match candidate party');
            }
            if ($cand_party === null) {
                // candidate without party: require selected party to be PARTEILOS
                $code = \App\Inc\env('PARTY_CODE_PARTEILOS', 'PARTEILOS');
                $st2 = $pdo->prepare('SELECT id FROM parties WHERE id = ? AND code = ? LIMIT 1');
                $st2->execute([$party_id, $code]);
                if (! $st2->fetchColumn()) {
                    throw new \RuntimeException('Candidate is independent; select the PARTEILOS party');
                }
            }
        }

        // duplicate check before storing: compute sha from tmp
        $sha = $validated['sha256'];
        $dup = $pdo->prepare('SELECT id FROM images WHERE sha256 = ? LIMIT 1');
        $dup->execute([$sha]);
        $existing = $dup->fetchColumn();
        if ($existing) {
            throw new \RuntimeException('Duplicate image exists with id ' . $existing);
        }

        $stored = \App\Inc\store_upload($validated);

        $ins = $pdo->prepare('INSERT INTO images (party_id, candidate_id, file_path, original_filename, mime, size_bytes, sha256, uploaded_by, election_id, locality_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ins->execute([$party_id, $candidate_id, $stored['file_path'], $_FILES['image']['name'], $validated['mime'], $validated['size_bytes'], $validated['sha256'], $_SESSION['user_id'] ?? null, $election_id, $locality_id]);
        $id = $pdo->lastInsertId();

        \App\Inc\flash_set('success', 'Image uploaded successfully! ID: ' . $id);
        \App\Inc\redirect('image.php?id=' . $id);

    } catch (\Throwable $e) {
        \App\Inc\flash_set('error', 'Upload failed: ' . $e->getMessage());
        \App\Inc\redirect('upload.php');
    }
}

?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>
<h1>Upload Image</h1>
<form method="post" enctype="multipart/form-data">
  <?php echo \App\Inc\csrf_input(); ?>
  <label>Image: <input type="file" name="image" accept="image/jpeg,image/png" required></label><br>
  <label>Election:
    <select name="election_id" required>
      <option value="">-- choose --</option>
      <?php foreach ($elections as $e): ?>
        <option value="<?php echo \App\Inc\h($e['id']); ?>"><?php echo \App\Inc\h($e['name']); ?></option>
      <?php endforeach; ?>
    </select>
  </label><br>
  <label>Locality:
    <select name="locality_id" required>
      <option value="">-- choose --</option>
      <?php foreach ($localities as $l): ?>
        <option value="<?php echo \App\Inc\h($l['id']); ?>"><?php echo \App\Inc\h($l['name']); ?></option>
      <?php endforeach; ?>
    </select>
  </label><br>
  <label>Party:
    <select name="party_id" required>
      <option value="">-- choose --</option>
      <?php foreach ($parties as $p): ?>
        <option value="<?php echo \App\Inc\h($p['id']); ?>"><?php echo \App\Inc\h($p['name']); ?> (<?php echo \App\Inc\h($p['code']); ?>)</option>
      <?php endforeach; ?>
    </select>
  </label><br>
  <label>Candidate (optional):
    <select name="candidate_id">
      <option value="">-- none --</option>
      <?php foreach ($candidates as $c): ?>
        <option value="<?php echo \App\Inc\h($c['id']); ?>"><?php echo \App\Inc\h($c['name']); ?></option>
      <?php endforeach; ?>
    </select>
  </label><br>
  <button type="submit">Upload</button>
</form>
<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
