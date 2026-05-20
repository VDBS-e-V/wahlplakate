<?php
namespace App\Inc;

/**
 * normalize_name(string $s): string
 * Normalize a name: trim, handle umlauts, remove diacritics, keep only letters & spaces
 */
function normalize_name(string $s): string
{
	// Trim and collapse multiple spaces
	$s = trim(preg_replace('/\s+/', ' ', $s));
	
	// Handle German umlauts
	$s = str_ireplace('ä', 'ae', $s);
	$s = str_ireplace('ö', 'oe', $s);
	$s = str_ireplace('ü', 'ue', $s);
	$s = str_ireplace('ß', 'ss', $s);
	
	// Try iconv translit if available
	if (extension_loaded('iconv')) {
		$s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
	}
	
	// Keep only letters and spaces
	$s = preg_replace('/[^a-zA-Z\s]/', '', $s);
	$s = trim(preg_replace('/\s+/', ' ', $s));
	
	return $s;
}

/**
 * name_code(string $fullName): string
 * Extract code from full name: "Schmitt, Marga" → "SchmMa"
 */
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

/**
 * rand_base36_2(): string
 * Generate 2 random characters from 0-9A-Z
 */
function rand_base36_2(): string
{
	$chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$c1 = $chars[random_int(0, 35)];
	$c2 = $chars[random_int(0, 35)];
	return $c1 . $c2;
}

/**
 * generate_candidate_code(PDO $pdo, string $partyCode, string $fullName): string
 * Generate unique candidate_code in format: partyCode-nameCode-randomBase36
 * Throws exception if cannot generate unique code after 50 attempts
 */
function generate_candidate_code(\PDO $pdo, string $partyCode, string $fullName): string
{
	$prefix = $partyCode . '-' . name_code($fullName) . '-';
	
	for ($i = 0; $i < 50; $i++) {
		$code = $prefix . rand_base36_2();
		
		$stmt = $pdo->prepare('SELECT 1 FROM candidates WHERE candidate_code = ? LIMIT 1');
		$stmt->execute([$code]);
		if (!$stmt->fetchColumn()) {
			return $code;
		}
	}
	
	throw new \RuntimeException('Could not generate unique candidate code after 50 attempts');
}
