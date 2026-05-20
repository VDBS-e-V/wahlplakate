<?php
namespace App\Inc;

/**
 * read_csv_uploaded(string $inputName, string $delimiter): array
 * Read CSV from uploaded file, return array of associative arrays
 * First row is treated as header
 */
function read_csv_uploaded(string $inputName, string $delimiter = ';'): array
{
	if (!isset($_FILES[$inputName])) {
		throw new \RuntimeException('No file uploaded with field: ' . $inputName);
	}
	
	$file = $_FILES[$inputName];
	
	if ($file['error'] !== UPLOAD_ERR_OK) {
		throw new \RuntimeException('Upload error: ' . $file['error']);
	}
	
	if (!is_uploaded_file($file['tmp_name'])) {
		throw new \RuntimeException('Invalid file upload');
	}
	
	$rows = [];
	$handle = fopen($file['tmp_name'], 'r');
	if (!$handle) {
		throw new \RuntimeException('Cannot open CSV file');
	}
	
	$header = null;
	$lineNum = 0;
	
	while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
		$lineNum++;
		
		// Skip empty lines
		if (count($line) === 1 && $line[0] === '') {
			continue;
		}
		
		// First non-empty line is header
		if ($header === null) {
			$header = array_map('trim', $line);
			continue;
		}
		
		// Build associative array from header and values
		$row = [];
		foreach ($header as $i => $key) {
			$row[$key] = isset($line[$i]) ? trim($line[$i]) : '';
		}
		
		$rows[] = $row;
	}
	
	fclose($handle);
	
	if ($header === null) {
		throw new \RuntimeException('CSV file is empty or has no header');
	}
	
	return $rows;
}
