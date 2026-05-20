<?php
namespace App\Inc;

function normalize_name(string $s): string
{
	$s = trim(preg_replace('/\s+/u', ' ', $s));
	$s = strtr($s, [
		'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
		'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
	]);

	if (function_exists('iconv')) {
		$transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
		if ($transliterated !== false) {
			$s = $transliterated;
		}
	}

	$s = preg_replace('/[^A-Za-z ]+/', '', $s) ?? '';
	$s = trim(preg_replace('/\s+/', ' ', $s) ?? '');

	return $s;
}

function name_code(string $fullName): string
{
	$normalized = normalize_name($fullName);
	$parts = explode(' ', $normalized);
	
	if (count($parts) < 2) {
		// Single word: use first 4 chars
		$last = $parts[0] ?? '';
		$first = '';
	} else {
		// Multi-word: first word = first name, last word = last name
		$first = $parts[0];
		$last = $parts[count($parts) - 1];
	}
	
	// Extract: first 2 from first name, first 4 from last name
	$first2 = substr($first, 0, 2);
	$last4 = substr($last, 0, 4);
	
	// Format: Capitalize first letter of each, rest lowercase
	$code = ucfirst(strtolower($last4)) . ucfirst(strtolower($first2));
	
	return $code;
}

function rand_base36_2(): string
{
	$chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$c1 = $chars[random_int(0, 35)];
	$c2 = $chars[random_int(0, 35)];
	return $c1 . $c2;
}

function generate_candidate_code(\PDO $pdo, string $partyCode, string $fullName): string
{
	$prefix = $partyCode . '-' . name_code($fullName) . '-';
	
	for ($i = 0; $i < 50; $i++) {
		$code = $prefix . rand_base36_2();
		
		$stmt = $pdo->prepare('SELECT 1 FROM election_candidates WHERE candidate_code = ? LIMIT 1');
		$stmt->execute([$code]);
		if (!$stmt->fetchColumn()) {
			return $code;
		}
	}
	
	throw new \RuntimeException('Could not generate unique candidate code after 50 attempts');
}
