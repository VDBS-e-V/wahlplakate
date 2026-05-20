
<?php
require_once __DIR__ . '/../../app/inc/csrf.php';
require_once __DIR__ . '/../../app/inc/util.php';

$flow = $flow ?? [];
$elections = $elections ?? [];
$step = (int) ($step ?? 1);
$electionId = (int) ($electionId ?? 0);

require_once __DIR__ . '/../../app/views/header.php';
?>
<h1>Bild hochladen</h1>

<?php if ($step === 1): ?>
    <form method="post">
        <?php echo \App\Inc\csrf_input(); ?>
        <input type="hidden" name="step" value="1">
        <label>Wahl auswählen
            <select name="election_id" required>
                <option value="">-- bitte wählen --</option>
                <?php foreach ($elections as $election): ?>
                    <option value="<?php echo (int) $election['id']; ?>" <?php echo (int) $election['id'] === $electionId ? 'selected' : ''; ?>><?php echo \App\Inc\h($election['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="margin-left:1rem;"><input type="checkbox" name="save_default" value="1"> Als Standard speichern</label>
        <br><br>
        <button class="btn" type="submit">Weiter</button>
    </form>
<?php elseif ($step === 2): ?>
    <?php
    $pdo = \App\Core\Database::pdo();
    $stmt = $pdo->prepare('SELECT id, name FROM wpl_districts WHERE election_id = ? ORDER BY name');
    $stmt->execute([$electionId]);
    $districts = $stmt->fetchAll();
    ?>
    <form method="post">
        <?php echo \App\Inc\csrf_input(); ?>
        <input type="hidden" name="step" value="2">
        <label>Bezirk / Kreis
            <select name="district_id" required>
                <option value="">-- bitte wählen --</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?php echo (int) $d['id']; ?>" <?php echo isset($flow['district_id']) && (int) $flow['district_id'] === (int) $d['id'] ? 'selected' : ''; ?>><?php echo \App\Inc\h($d['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <br><br>
        <a class="btn-link" href="upload.php?step=1&change_election=1">Wahl ändern</a>
        <button class="btn" type="submit" style="margin-left:1rem;">Weiter</button>
    </form>
<?php elseif ($step === 3): ?>
    <?php
    $pdo = \App\Core\Database::pdo();
    $districtId = (int) ($flow['district_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, name FROM wpl_localities WHERE district_id = ? ORDER BY name');
    $stmt->execute([$districtId]);
    $localities = $stmt->fetchAll();
    ?>
    <form method="post">
        <?php echo \App\Inc\csrf_input(); ?>
        <input type="hidden" name="step" value="3">
        <label>Ortsteil / Gemeinde
            <select name="locality_id" required>
                <option value="">-- bitte wählen --</option>
                <?php foreach ($localities as $l): ?>
                    <option value="<?php echo (int) $l['id']; ?>" <?php echo isset($flow['locality_id']) && (int) $flow['locality_id'] === (int) $l['id'] ? 'selected' : ''; ?>><?php echo \App\Inc\h($l['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <br><br>
        <a class="btn-link" href="upload.php?step=2">Zurück</a>
        <button class="btn" type="submit" style="margin-left:1rem;">Weiter</button>
    </form>
<?php elseif ($step === 4): ?>
    <?php
    $pdo = \App\Core\Database::pdo();
    $stmt = $pdo->prepare('SELECT ep.id, p.code, COALESCE(ep.ballot_label, p.name) AS label FROM wpl_election_parties ep INNER JOIN wpl_parties p ON p.id = ep.party_id WHERE ep.election_id = ? ORDER BY COALESCE(ep.ballot_label, p.name)');
    $stmt->execute([$electionId]);
    $electionParties = $stmt->fetchAll();
    ?>
    <form method="post" id="step4form">
        <?php echo \App\Inc\csrf_input(); ?>
        <input type="hidden" name="step" value="4">
        <label>Partei
            <?php
                $selectedPartyLabel = '';
                $selectedPartyId = isset($flow['election_party_id']) ? (int) $flow['election_party_id'] : 0;
                foreach ($electionParties as $p) {
                    if ($selectedPartyId && (int)$p['id'] === $selectedPartyId) {
                        $selectedPartyLabel = $p['label'] . ' / ' . $p['code'];
                        break;
                    }
                }
            ?>
            <input list="election_parties_list" id="election_party_input" name="election_party_input" required value="<?php echo \App\Inc\h($selectedPartyLabel); ?>" placeholder="Wähle oder tippe eine Partei">
            <datalist id="election_parties_list">
                <?php foreach ($electionParties as $p): ?>
                    <option data-id="<?php echo (int) $p['id']; ?>" value="<?php echo \App\Inc\h($p['label'] . ' / ' . $p['code']); ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <input type="hidden" name="election_party_id" id="election_party_id" value="<?php echo $selectedPartyId ? (int)$selectedPartyId : ''; ?>">
        </label>
        <br><br>
        <a class="btn-link" href="upload.php?step=3">Zurück</a>
        <button class="btn" type="submit" style="margin-left:1rem;">Weiter</button>
    </form>
    <script>
    (function(){
        const list = document.getElementById('election_parties_list');
        const input = document.getElementById('election_party_input');
        const hidden = document.getElementById('election_party_id');
        if (!input || !list || !hidden) return;
        // map values to ids
        const options = Array.from(list.options || []);
        function sync() {
            hidden.value = '';
            const v = input.value || '';
            for (const opt of options) {
                if (opt.value === v) { hidden.value = opt.dataset.id || ''; break; }
            }
        }
        input.addEventListener('input', sync);
        // ensure sync before submit
        document.getElementById('step4form').addEventListener('submit', sync);
    })();
    </script>
<?php elseif ($step === 5): ?>
    <?php
    $pdo = \App\Core\Database::pdo();
    $stmt = $pdo->prepare('SELECT ec.id, ec.name, p.code AS party_code, COALESCE(ep.ballot_label, p.name) AS party_label FROM wpl_election_candidates ec INNER JOIN wpl_election_parties ep ON ep.id = ec.election_party_id INNER JOIN wpl_parties p ON p.id = ep.party_id WHERE ec.election_id = ? ORDER BY ec.name');
    $stmt->execute([$electionId]);
    $candidates = $stmt->fetchAll();
    ?>
    <form method="post" id="step5form">
        <?php echo \App\Inc\csrf_input(); ?>
        <input type="hidden" name="step" value="5">
        <label>Kandidat*in (optional)
            <?php
                $selectedCandidateLabel = '';
                $selectedCandidateId = isset($flow['election_candidate_id']) && $flow['election_candidate_id'] !== null ? (int)$flow['election_candidate_id'] : 0;
                foreach ($candidates as $c) {
                    if ($selectedCandidateId && (int)$c['id'] === $selectedCandidateId) {
                        $selectedCandidateLabel = $c['name'] . ' (' . ($c['party_label'] . ' / ' . $c['party_code']) . ')';
                        break;
                    }
                }
            ?>
            <input list="election_candidates_list" id="election_candidate_input" name="election_candidate_input" value="<?php echo \App\Inc\h($selectedCandidateLabel); ?>" placeholder="Wähle oder tippe eine Kandidat*in">
            <datalist id="election_candidates_list">
                <?php foreach ($candidates as $c): ?>
                    <option data-id="<?php echo (int) $c['id']; ?>" value="<?php echo \App\Inc\h($c['name'] . ' (' . ($c['party_label'] . ' / ' . $c['party_code']) . ')'); ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <input type="hidden" name="election_candidate_id" id="election_candidate_id" value="<?php echo $selectedCandidateId ? (int)$selectedCandidateId : ''; ?>">
        </label>
        <br><br>
        <a href="upload.php?step=4">Zurück</a>
        <button type="submit" style="margin-left:1rem;">Weiter</button>
    </form>
    <script>
    (function(){
        const list = document.getElementById('election_candidates_list');
        const input = document.getElementById('election_candidate_input');
        const hidden = document.getElementById('election_candidate_id');
        if (!input || !list || !hidden) return;
        const options = Array.from(list.options || []);
        function sync() {
            hidden.value = '';
            const v = input.value || '';
            for (const opt of options) {
                if (opt.value === v) { hidden.value = opt.dataset.id || ''; break; }
            }
        }
        input.addEventListener('input', sync);
        document.getElementById('step5form').addEventListener('submit', sync);
    })();
    </script>
<?php elseif ($step === 6): ?>
    <?php
    $pdo = \App\Core\Database::pdo();
    $stmt = $pdo->prepare('SELECT name FROM wpl_elections WHERE id = ? LIMIT 1');
    $stmt->execute([$electionId]);
    $electionName = $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT name FROM wpl_districts WHERE id = ? LIMIT 1');
    $stmt->execute([(int) ($flow['district_id'] ?? 0)]);
    $districtName = $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT name FROM wpl_localities WHERE id = ? LIMIT 1');
    $stmt->execute([(int) ($flow['locality_id'] ?? 0)]);
    $localityName = $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT ep.id, p.code, COALESCE(ep.ballot_label, p.name) AS label FROM wpl_election_parties ep INNER JOIN wpl_parties p ON p.id = ep.party_id WHERE ep.id = ? LIMIT 1');
    $stmt->execute([(int) ($flow['election_party_id'] ?? 0)]);
    $partyRow = $stmt->fetch();
    $stmt = $pdo->prepare('SELECT name FROM wpl_election_candidates WHERE id = ? LIMIT 1');
    $stmt->execute([(int) ($flow['election_candidate_id'] ?? 0)]);
    $candidateName = $stmt->fetchColumn();
    ?>
    <h3>Zusammenfassung</h3>
    <ul>
        <li>Wahl: <?php echo \App\Inc\h((string) $electionName); ?></li>
        <li>Bezirk/Kreis: <?php echo \App\Inc\h((string) $districtName); ?></li>
        <li>Ortsteil/Gemeinde: <?php echo \App\Inc\h((string) $localityName); ?></li>
        <li>Partei: <?php echo \App\Inc\h((string) ($partyRow['label'] ?? '')); ?> (<?php echo \App\Inc\h((string) ($partyRow['code'] ?? '')); ?>)</li>
        <li>Kandidat*in: <?php echo \App\Inc\h((string) ($candidateName ?? '—')); ?></li>
    </ul>
    <form method="post" enctype="multipart/form-data">
        <?php echo \App\Inc\csrf_input(); ?>
        <input type="hidden" name="step" value="6">
        <label>Bild: <input type="file" name="image" accept="image/jpeg,image/png" required></label><br><br>
        <a href="upload.php?step=5">Zurück</a>
        <button type="submit" style="margin-left:1rem;">Hochladen</button>
            <button class="btn" type="submit" style="margin-left:1rem;">Hochladen</button>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
