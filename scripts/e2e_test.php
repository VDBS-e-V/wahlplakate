<?php
// E2E CLI test: store_upload -> DB insert -> replace -> delete
require_once __DIR__ . '/../app/inc/env.php';
require_once __DIR__ . '/../app/inc/db.php';
require_once __DIR__ . '/../app/inc/image_store.php';
require_once __DIR__ . '/../app/inc/util.php';

use App\Inc;

$pdo = Inc\db();

echo "Starting E2E CLI test\n";

function make_png($path, $w = 200, $h = 120, $text = 'T'){
    if (!function_exists('imagecreatetruecolor')){
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII='));
        return;
    }
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 220, 235, 255);
    $fg = imagecolorallocate($img, 40, 60, 90);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);
    imagestring($img, 5, 8, 8, $text, $fg);
    imagepng($img, $path);
    imagedestroy($img);
}

$tmp1 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpl_e2e_1.png';
$tmp2 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vpl_e2e_2.png';
@unlink($tmp1);
@unlink($tmp2);
make_png($tmp1, 300, 180, 'E2E-1');
make_png($tmp2, 300, 180, 'E2E-2');

$validated1 = [
    'mime' => 'image/png',
    'ext' => 'png',
    'size_bytes' => filesize($tmp1),
    'sha256' => hash_file('sha256', $tmp1),
    'tmp' => $tmp1,
];
$validated2 = [
    'mime' => 'image/png',
    'ext' => 'png',
    'size_bytes' => filesize($tmp2),
    'sha256' => hash_file('sha256', $tmp2),
    'tmp' => $tmp2,
];

// Ensure minimal seed data
$election = $pdo->query('SELECT id,name FROM wpl_elections LIMIT 1')->fetch();
if (! $election) {
    $pdo->exec("INSERT INTO wpl_elections (name) VALUES ('Test Election')");
    $electionId = $pdo->lastInsertId();
    echo "Inserted test election id={$electionId}\n";
    $election = ['id' => $electionId, 'name' => 'Test Election'];
}
$electionId = (int)$election['id'];

$party = $pdo->query('SELECT id,name,code FROM wpl_parties LIMIT 1')->fetch();
if (! $party) {
    $code = 'TP' . substr(bin2hex(random_bytes(2)),0,3);
    $pdo->prepare('INSERT INTO wpl_parties (name, code) VALUES (?, ?)')->execute(['Test Party', $code]);
    $partyId = $pdo->lastInsertId();
    echo "Inserted test party id={$partyId}\n";
    $party = ['id' => $partyId, 'name' => 'Test Party', 'code' => $code];
}
$partyId = (int)$party['id'];

$ep = $pdo->prepare('SELECT id FROM wpl_election_parties WHERE election_id = ? AND party_id = ? LIMIT 1');
$ep->execute([$electionId, $partyId]);
$epRow = $ep->fetch();
if (! $epRow) {
    $stmt = $pdo->prepare('INSERT INTO wpl_election_parties (election_id, party_id, ballot_label) VALUES (?, ?, ?)');
    $stmt->execute([$electionId, $partyId, 'TestPartyLabel']);
    $epId = $pdo->lastInsertId();
    echo "Inserted election_party id={$epId}\n";
    $epRow = ['id' => $epId];
}
$epId = (int)$epRow['id'];

$district = $pdo->prepare('SELECT id FROM wpl_districts WHERE election_id = ? LIMIT 1');
$district->execute([$electionId]);
$districtRow = $district->fetch();
if (! $districtRow) {
    $pdo->prepare('INSERT INTO wpl_districts (election_id, name) VALUES (?, ?)')->execute([$electionId, 'Test District']);
    $districtId = $pdo->lastInsertId();
    echo "Inserted district id={$districtId}\n";
    $districtRow = ['id' => $districtId];
}
$districtId = (int)$districtRow['id'];

$locality = $pdo->prepare('SELECT id FROM wpl_localities WHERE district_id = ? LIMIT 1');
$locality->execute([$districtId]);
$localRow = $locality->fetch();
if (! $localRow) {
    $pdo->prepare('INSERT INTO wpl_localities (district_id, name) VALUES (?, ?)')->execute([$districtId, 'Test Locality']);
    $localityId = $pdo->lastInsertId();
    echo "Inserted locality id={$localityId}\n";
    $localRow = ['id' => $localityId];
}
$localityId = (int)$localRow['id'];

$cand = $pdo->prepare('SELECT id FROM wpl_election_candidates WHERE election_id = ? LIMIT 1');
$cand->execute([$electionId]);
$candRow = $cand->fetch();
if (! $candRow) {
    $code = 'C' . substr(bin2hex(random_bytes(2)),0,3);
    $pdo->prepare('INSERT INTO wpl_election_candidates (election_id, election_party_id, candidate_code, name) VALUES (?, ?, ?, ?)')->execute([$electionId, $epId, $code, 'Test Candidate']);
    $candId = $pdo->lastInsertId();
    echo "Inserted candidate id={$candId}\n";
    $candRow = ['id' => $candId];
}
$candId = (int)$candRow['id'];

// Store first upload
$meta = [
    'election' => $election['name'] ?? '',
    'locality' => 'Test Locality',
    'party' => 'TestPartyLabel',
    'candidate' => 'Test Candidate',
];
try {
    $stored1 = Inc\store_upload($validated1, $meta);
    echo "Stored file: " . $stored1['file_path'] . "\n";
} catch (Throwable $e) {
    echo "store_upload failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Insert DB row
$ins = $pdo->prepare('INSERT INTO wpl_images (election_id, election_party_id, election_candidate_id, locality_id, file_path, original_filename, mime, size_bytes, sha256, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$ins->execute([$electionId, $epId, $candId, $localityId, $stored1['file_path'], basename($tmp1), $validated1['mime'], $validated1['size_bytes'], $validated1['sha256'], null]);
$imageId = $pdo->lastInsertId();

echo "Inserted image row id={$imageId}\n";

// Verify file exists
$uploadDir = Inc\env_required('UPLOAD_DIR');
$abs1 = rtrim($uploadDir, "\\/") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stored1['file_path']);
if (file_exists($abs1)) {
    echo "File exists on disk: {$abs1}\n";
} else {
    echo "File NOT found at: {$abs1}\n";
}

// Replace: store second file and update row, delete old file
try {
    $stored2 = Inc\store_upload($validated2, $meta);
    echo "Stored replacement file: " . $stored2['file_path'] . "\n";
} catch (Throwable $e) {
    echo "store_upload (replace) failed: " . $e->getMessage() . "\n";
    exit(1);
}

$upd = $pdo->prepare('UPDATE wpl_images SET file_path = ?, original_filename = ?, mime = ?, size_bytes = ?, sha256 = ? WHERE id = ?');
$upd->execute([$stored2['file_path'], basename($tmp2), $validated2['mime'], $validated2['size_bytes'], $validated2['sha256'], $imageId]);

// delete old file
Inc\delete_stored_file($stored1['file_path']);

$abs2 = rtrim($uploadDir, "\\/") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stored2['file_path']);
if (file_exists($abs2)) {
    echo "Replacement file exists: {$abs2}\n";
} else {
    echo "Replacement file NOT found: {$abs2}\n";
}
if (!file_exists($abs1)) {
    echo "Old file successfully deleted.\n";
} else {
    echo "Old file still present (expected deleted): {$abs1}\n";
}

// Delete DB row and file
$del = $pdo->prepare('DELETE FROM wpl_images WHERE id = ?');
$del->execute([$imageId]);
Inc\delete_stored_file($stored2['file_path']);
if (! file_exists($abs2)) {
    echo "Replacement file deleted successfully.\n";
} else {
    echo "Replacement file still present after delete: {$abs2}\n";
}

echo "E2E CLI test finished.\n";

// cleanup tmp files
@unlink($tmp1);
@unlink($tmp2);

return 0;
