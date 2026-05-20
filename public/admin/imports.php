<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/util.php';

$pageTitle = 'Admin Imports';
\App\Inc\require_admin();
?>
<?php require_once __DIR__ . '/../../app/views/header.php'; ?>
<h1>Admin Imports</h1>
<p>Manage data imports for Wahlplakate system.</p>
<ul>
	<li><a href="import_parties.php">Import Parties</a></li>
	<li><a href="import_regions.php">Import Regions</a></li>
	<li><a href="import_elections.php">Import Elections</a></li>
	<li><a href="import_candidates.php">Import Candidates</a></li>
</ul>
<?php require_once __DIR__ . '/../../app/views/footer.php'; ?>
