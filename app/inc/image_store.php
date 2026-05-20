<?php
namespace App\Inc;

require_once __DIR__ . '/env.php';

const IMAGE_MAX_BYTES = 15728640; // 15MB

function validate_upload(array $file): array
{
    if (! isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('Upload error');
    }
    if (! isset($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
        throw new \RuntimeException('No uploaded file');
    }
    $tmp = $file['tmp_name'];
    $size = filesize($tmp);
    if ($size === false) {
        throw new \RuntimeException('Cannot determine file size');
    }
    if ($size > IMAGE_MAX_BYTES) {
        throw new \RuntimeException('File too large');
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if ($mime !== 'image/jpeg' && $mime !== 'image/png') {
        throw new \RuntimeException('Unsupported image type');
    }
    $img = @getimagesize($tmp);
    if ($img === false) {
        throw new \RuntimeException('Invalid image file');
    }
    $ext = $mime === 'image/png' ? 'png' : 'jpg';
    $sha = hash_file('sha256', $tmp);

    return [
        'mime' => $mime,
        'ext' => $ext,
        'size_bytes' => $size,
        'sha256' => $sha,
        'tmp' => $tmp,
    ];
}

function slugify(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    // try transliteration to ASCII
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($trans !== false && $trans !== '') {
        $s = $trans;
    }
    $s = mb_strtolower($s, 'UTF-8');
    // remove characters that are not letters, numbers, spaces, dash or underscore
    $s = preg_replace('/[^\p{L}\p{N}\s\-_]+/u', '', $s);
    // spaces and slashes to dash
    $s = preg_replace('/[\s\/]+/u', '-', $s);
    // collapse multiple dashes/underscores
    $s = preg_replace('/[_\-]+/u', '_', $s);
    $s = preg_replace('/-+/', '-', $s);
    $s = trim($s, "-_ ");
    // final strict filter
    $s = preg_replace('/[^A-Za-z0-9\-_]/', '', $s);
    return mb_substr($s, 0, 100, 'UTF-8');
}

/**
 * Store uploaded image file on disk.
 * Optionally pass $meta with readable labels to include in filename:
 * ['election' => 'Name', 'locality' => 'Ortsteil', 'party' => 'BallotLabel', 'candidate' => 'Name']
 */
function store_upload(array $validated, array $meta = []): array
{
    $uploadDir = env_required('UPLOAD_DIR');
    $dt = new \DateTimeImmutable('now');

    // build descriptive name parts
    $labels = [];
    if (! empty($meta['election'])) {
        $labels[] = slugify((string) $meta['election']);
    }
    if (! empty($meta['locality'])) {
        $labels[] = slugify((string) $meta['locality']);
    }
    if (! empty($meta['party'])) {
        $labels[] = slugify((string) $meta['party']);
    }
    if (! empty($meta['candidate'])) {
        $labels[] = slugify((string) $meta['candidate']);
    }

    $baseName = implode('_', array_filter($labels));
    if ($baseName === '') {
        $baseName = substr($validated['sha256'], 0, 12);
    }

    try {
        // generate a short random hex string, max 5 characters
        $rand = substr(bin2hex(random_bytes(3)), 0, 5);
    } catch (\Throwable $e) {
        $rand = substr($validated['sha256'], 0, 5);
    }

    $fileName = $baseName . '_' . $rand . '_' . $validated['sha256'] . '.' . $validated['ext'];
    $rel = sprintf('%s/%s/%s', $dt->format('Y'), $dt->format('m'), $fileName);
    $rel = str_replace('\\', '/', $rel);
    $abs = rtrim($uploadDir, "\\/") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    $dir = dirname($abs);
    if (! is_dir($dir)) {
        if (! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Failed to create upload directory');
        }
    }

    if (! move_uploaded_file($validated['tmp'], $abs)) {
        // As fallback try rename (for testing via CLI where is_uploaded_file may fail)
        if (! @rename($validated['tmp'], $abs)) {
            throw new \RuntimeException('Failed to move uploaded file');
        }
    }

    return ['file_path' => $rel, 'abs_path' => $abs];
}

function delete_stored_file(string $relPath): void
{
    $uploadDir = env_required('UPLOAD_DIR');
    $abs = rtrim($uploadDir, "\\/") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    $realBase = realpath($uploadDir);
    $realTarget = realpath($abs);
    if ($realTarget === false || $realBase === false) {
        return;
    }
    // ensure target is inside uploads dir
    if (str_starts_with($realTarget, $realBase)) {
        @unlink($realTarget);
    }
}
