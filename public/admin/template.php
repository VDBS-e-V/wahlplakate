<?php
require_once __DIR__ . '/../../app/inc/auth.php';
require_once __DIR__ . '/../../app/inc/util.php';

\App\Inc\require_admin();

$name = strtolower(trim((string) ($_GET['name'] ?? '')));
$map = [
	'election_parties' => __DIR__ . '/../../app/templates/election_parties_template.csv',
	'regions' => __DIR__ . '/../../app/templates/regions_template.csv',
	'candidates' => __DIR__ . '/../../app/templates/candidates_template.csv',
];

if (!isset($map[$name])) {
	http_response_code(404);
	echo 'Template not found';
	exit;
}

$abs = $map[$name];
if (!is_file($abs)) {
	http_response_code(404);
	echo 'Template file missing';
	exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '_template.csv"');
header('Content-Length: ' . filesize($abs));
readfile($abs);
exit;