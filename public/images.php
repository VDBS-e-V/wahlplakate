<?php
require_once __DIR__ . '/../app/inc/auth.php';
require_once __DIR__ . '/../app/inc/db.php';
require_once __DIR__ . '/../app/inc/util.php';

\App\Inc\require_login();
$pdo = \App\Inc\db();

$pageTitle = 'Alle Bilder';

$filterElectionId = isset($_GET['election_id']) ? max(0, (int) $_GET['election_id']) : 0;
$filterDistrictId = isset($_GET['district_id']) ? max(0, (int) $_GET['district_id']) : 0;
$filterLocalityId = isset($_GET['locality_id']) ? max(0, (int) $_GET['locality_id']) : 0;
$filterPartyId = isset($_GET['election_party_id']) ? max(0, (int) $_GET['election_party_id']) : 0;
$filterCandidateId = isset($_GET['election_candidate_id']) ? max(0, (int) $_GET['election_candidate_id']) : 0;

$perPage = 48;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$elections = $pdo->query('SELECT id, name FROM wpl_elections ORDER BY name')->fetchAll();

$districtSql = 'SELECT d.id, d.name, e.name AS election_name FROM wpl_districts d INNER JOIN wpl_elections e ON e.id = d.election_id';
$districtParams = [];
if ($filterElectionId > 0) {
    $districtSql .= ' WHERE d.election_id = ?';
    $districtParams[] = $filterElectionId;
}
$districtSql .= ' ORDER BY e.name, d.name';
$districtStmt = $pdo->prepare($districtSql);
$districtStmt->execute($districtParams);
$districts = $districtStmt->fetchAll();

$localitySql = 'SELECT l.id, l.name, d.name AS district_name FROM wpl_localities l INNER JOIN wpl_districts d ON d.id = l.district_id';
$localityParams = [];
$localityWhere = [];
if ($filterElectionId > 0) {
    $localityWhere[] = 'd.election_id = ?';
    $localityParams[] = $filterElectionId;
}
if ($filterDistrictId > 0) {
    $localityWhere[] = 'l.district_id = ?';
    $localityParams[] = $filterDistrictId;
}
if ($localityWhere) {
    $localitySql .= ' WHERE ' . implode(' AND ', $localityWhere);
}
$localitySql .= ' ORDER BY d.name, l.name';
$localityStmt = $pdo->prepare($localitySql);
$localityStmt->execute($localityParams);
$localities = $localityStmt->fetchAll();

$partySql = 'SELECT ep.id, COALESCE(ep.ballot_label, p.name) AS party_label, p.code, e.name AS election_name FROM wpl_election_parties ep INNER JOIN wpl_parties p ON p.id = ep.party_id INNER JOIN wpl_elections e ON e.id = ep.election_id';
$partyParams = [];
if ($filterElectionId > 0) {
    $partySql .= ' WHERE ep.election_id = ?';
    $partyParams[] = $filterElectionId;
}
$partySql .= ' ORDER BY e.name, COALESCE(ep.ballot_label, p.name)';
$partyStmt = $pdo->prepare($partySql);
$partyStmt->execute($partyParams);
$parties = $partyStmt->fetchAll();

$candidateSql = 'SELECT ec.id, ec.name, COALESCE(ep.ballot_label, p.name) AS party_label, p.code, e.name AS election_name FROM wpl_election_candidates ec INNER JOIN wpl_election_parties ep ON ep.id = ec.election_party_id INNER JOIN wpl_parties p ON p.id = ep.party_id INNER JOIN wpl_elections e ON e.id = ec.election_id';
$candidateParams = [];
$candidateWhere = [];
if ($filterElectionId > 0) {
    $candidateWhere[] = 'ec.election_id = ?';
    $candidateParams[] = $filterElectionId;
}
if ($filterPartyId > 0) {
    $candidateWhere[] = 'ec.election_party_id = ?';
    $candidateParams[] = $filterPartyId;
}
if ($candidateWhere) {
    $candidateSql .= ' WHERE ' . implode(' AND ', $candidateWhere);
}
$candidateSql .= ' ORDER BY e.name, ec.name';
$candidateStmt = $pdo->prepare($candidateSql);
$candidateStmt->execute($candidateParams);
$candidates = $candidateStmt->fetchAll();

$where = [];
$params = [];
if ($filterElectionId > 0) {
    $where[] = 'i.election_id = ?';
    $params[] = $filterElectionId;
}
if ($filterDistrictId > 0) {
    $where[] = 'd.id = ?';
    $params[] = $filterDistrictId;
}
if ($filterLocalityId > 0) {
    $where[] = 'i.locality_id = ?';
    $params[] = $filterLocalityId;
}
if ($filterPartyId > 0) {
    $where[] = 'i.election_party_id = ?';
    $params[] = $filterPartyId;
}
if ($filterCandidateId > 0) {
    $where[] = 'i.election_candidate_id = ?';
    $params[] = $filterCandidateId;
}

$fromSql = ' FROM wpl_images i LEFT JOIN wpl_localities l ON i.locality_id = l.id LEFT JOIN wpl_districts d ON d.id = l.district_id LEFT JOIN wpl_elections e ON i.election_id = e.id LEFT JOIN wpl_election_parties ep ON i.election_party_id = ep.id LEFT JOIN wpl_parties p ON p.id = ep.party_id LEFT JOIN wpl_election_candidates ec ON i.election_candidate_id = ec.id LEFT JOIN wpl_users u ON i.uploaded_by = u.id';
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*)' . $fromSql . $whereSql);
$countStmt->execute($params);
$count = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($count / $perPage));

$offsetSql = (int) $offset;
$limitSql = (int) $perPage;

$listSql = 'SELECT i.id, i.created_at, i.original_filename, i.file_path, i.size_bytes, e.name AS election_name, d.name AS district_name, l.name AS locality_name, COALESCE(ep.ballot_label, p.name) AS party_label, p.code AS party_code, ec.name AS candidate_name, u.email AS uploader_email' . $fromSql . $whereSql . " ORDER BY i.created_at DESC, i.id DESC LIMIT {$offsetSql}, {$limitSql}";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$images = $listStmt->fetchAll();

$paginationParams = $_GET;
unset($paginationParams['page']);

$activeFilterCount = 0;
foreach ([$filterElectionId, $filterDistrictId, $filterLocalityId, $filterPartyId, $filterCandidateId] as $activeFilterValue) {
    if ((int) $activeFilterValue > 0) {
        $activeFilterCount++;
    }
}

?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>
<style>
    .search-panel {
        margin: 0 0 1.25rem;
        padding: 1rem;
        border: 1px solid #d8e3ef;
        border-radius: 10px;
        background: linear-gradient(135deg, #f7fbff 0%, #edf6ff 100%);
    }
    .search-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.75rem;
        margin-bottom: 0.85rem;
        flex-wrap: wrap;
    }
    .search-title {
        margin: 0;
        font-size: 1.05rem;
        color: #17436f;
    }
    .search-subtitle {
        margin: 0;
        font-size: 0.9rem;
        color: #365770;
        background: #e7f2fc;
        padding: 0.25rem 0.55rem;
        border-radius: 999px;
    }
    .search-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.75rem;
    }
    .search-field label {
        display: block;
        margin: 0 0 0.35rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: #25537f;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
    .search-field select {
        width: 100%;
        border: 1px solid #b7cbe0;
        border-radius: 8px;
        background: #fff;
    }
    .search-actions {
        display: flex;
        gap: 0.55rem;
        align-items: flex-end;
    }
    .search-actions .btn-secondary {
        display: inline-block;
        padding: 0.5rem 0.75rem;
        border: 1px solid #9eb6cc;
        border-radius: 8px;
        text-decoration: none;
        color: #27445e;
        background: #ffffff;
    }
    .search-actions .btn-secondary:hover {
        background: #f2f8fe;
    }
</style>
<h1>Alle Bilder</h1>

<section class="search-panel">
    <div class="search-head">
        <h2 class="search-title">Suche und Filter</h2>
        <p class="search-subtitle"><?php echo (int) $count; ?> Treffer<?php echo $activeFilterCount > 0 ? ' · ' . (int) $activeFilterCount . ' Filter aktiv' : ''; ?></p>
    </div>

    <form method="get" class="search-grid">
        <div class="search-field">
            <label for="filter-election">Wahl</label>
            <select id="filter-election" name="election_id">
                <option value="0">Alle</option>
                <?php foreach ($elections as $election): ?>
                    <option value="<?php echo (int) $election['id']; ?>" <?php echo $filterElectionId === (int) $election['id'] ? 'selected' : ''; ?>>
                        <?php echo \App\Inc\h($election['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-field">
            <label for="filter-district">Bezirk</label>
            <select id="filter-district" name="district_id">
                <option value="0">Alle</option>
                <?php foreach ($districts as $district): ?>
                    <option value="<?php echo (int) $district['id']; ?>" <?php echo $filterDistrictId === (int) $district['id'] ? 'selected' : ''; ?>>
                        <?php echo \App\Inc\h($district['name'] . ' (' . $district['election_name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-field">
            <label for="filter-locality">Ortsteil</label>
            <select id="filter-locality" name="locality_id">
                <option value="0">Alle</option>
                <?php foreach ($localities as $locality): ?>
                    <option value="<?php echo (int) $locality['id']; ?>" <?php echo $filterLocalityId === (int) $locality['id'] ? 'selected' : ''; ?>>
                        <?php echo \App\Inc\h($locality['name'] . ' (' . $locality['district_name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-field">
            <label for="filter-party">Partei</label>
            <select id="filter-party" name="election_party_id">
                <option value="0">Alle</option>
                <?php foreach ($parties as $party): ?>
                    <option value="<?php echo (int) $party['id']; ?>" <?php echo $filterPartyId === (int) $party['id'] ? 'selected' : ''; ?>>
                        <?php echo \App\Inc\h($party['party_label'] . ' / ' . $party['code'] . ' (' . $party['election_name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-field">
            <label for="filter-candidate">Kandidat*in</label>
            <select id="filter-candidate" name="election_candidate_id">
                <option value="0">Alle</option>
                <?php foreach ($candidates as $candidate): ?>
                    <option value="<?php echo (int) $candidate['id']; ?>" <?php echo $filterCandidateId === (int) $candidate['id'] ? 'selected' : ''; ?>>
                        <?php echo \App\Inc\h($candidate['name'] . ' (' . $candidate['party_label'] . ' / ' . $candidate['code'] . ', ' . $candidate['election_name'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="search-actions">
            <button type="submit">Suchen</button>
            <a href="images.php" class="btn-secondary">Reset</a>
        </div>
    </form>
</section>

<?php if (empty($images)): ?>
    <p>Keine Bilder gefunden.</p>
<?php else: ?>
    <div class="gallery-grid">
    <?php foreach ($images as $img): ?>
        <div class="card">
            <a class="card-link" href="image_view.php?id=<?php echo \App\Inc\h((string)$img['id']); ?>">
                <img class="card-img" src="image.php?id=<?php echo \App\Inc\h((string)$img['id']); ?>" alt="">
            </a>
            <div class="card-body">
                <strong><?php echo \App\Inc\h($img['election_name'] ?? ''); ?></strong><br>
                <span class="muted"><?php echo \App\Inc\h(($img['district_name'] ?? '') . ' / ' . ($img['locality_name'] ?? '')); ?></span><br>
                <span class="muted"><?php echo \App\Inc\h(($img['party_label'] ?? '') . ((isset($img['party_code']) && $img['party_code'] !== '') ? (' / ' . $img['party_code']) : '')); ?></span><br>
                <span class="muted"><?php echo \App\Inc\h($img['candidate_name'] ?? ''); ?></span><br>
                <small>hochgeladen: <?php echo \App\Inc\h((string)\App\Inc\format_date_de($img['created_at'] ?? '')); ?></small><br>
                <small>Datei: <?php echo \App\Inc\h($img['original_filename'] ?? ''); ?></small>
                <div class="card-actions">
                    <a class="btn btn-sm" href="image_view.php?id=<?php echo \App\Inc\h((string)$img['id']); ?>">Öffnen</a>
                    <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo \App\Inc\h((string)$img['id']); ?>">Löschen</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div style="margin-top:1rem">
        <?php if ($page > 1): ?>
            <a href="images.php?<?php echo \App\Inc\h(http_build_query(array_merge($paginationParams, ['page' => $page - 1]))); ?>">&laquo; Vorherige</a>
        <?php endif; ?>
        &nbsp; Seite <?php echo $page; ?> / <?php echo $pages; ?> &nbsp;
        <?php if ($page < $pages): ?>
            <a href="images.php?<?php echo \App\Inc\h(http_build_query(array_merge($paginationParams, ['page' => $page + 1]))); ?>">Nächste &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
