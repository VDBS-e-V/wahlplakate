<?php
namespace App\Inc;

function read_csv_uploaded(string $inputName, string $delimiter = ';'): array
{
	if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
		throw new \RuntimeException('No file uploaded with field: ' . $inputName);
	}

	$file = $_FILES[$inputName];
	$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($error !== UPLOAD_ERR_OK) {
		$messages = [
			UPLOAD_ERR_INI_SIZE => 'Uploaded file exceeds php.ini upload_max_filesize',
			UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the form limit',
			UPLOAD_ERR_PARTIAL => 'Uploaded file was only partially uploaded',
			UPLOAD_ERR_NO_FILE => 'No file uploaded',
			UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory',
			UPLOAD_ERR_CANT_WRITE => 'Failed to write uploaded file to disk',
			UPLOAD_ERR_EXTENSION => 'Upload stopped by a PHP extension',
		];

		throw new \RuntimeException($messages[$error] ?? ('Upload error: ' . $error));
	}

	if (!is_uploaded_file($file['tmp_name'])) {
		throw new \RuntimeException('Invalid file upload');
	}

	$handle = fopen($file['tmp_name'], 'rb');
	if ($handle === false) {
		throw new \RuntimeException('Cannot open CSV file');
	}

	try {
		$header = null;
		$rows = [];

		while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
			$values = array_map(static fn($value) => trim((string) $value), $line);
			$nonEmptyValues = array_filter($values, static fn($value) => $value !== '');

			if ($nonEmptyValues === []) {
				continue;
			}

			if ($header === null) {
				$header = array_map(static function ($column) {
					$column = preg_replace('/^\xEF\xBB\xBF/', '', (string) $column);
					return trim($column);
				}, $values);
				continue;
			}

			$row = [];
			foreach ($header as $index => $key) {
				if ($key === '') {
					continue;
				}
				$row[$key] = $values[$index] ?? '';
			}

			$rows[] = $row;
		}

		if ($header === null) {
			throw new \RuntimeException('CSV file is empty or has no header');
		}

		return $rows;
	} finally {
		fclose($handle);
	}
}
