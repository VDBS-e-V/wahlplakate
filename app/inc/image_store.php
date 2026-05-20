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

function store_upload(array $validated): array
{
    $uploadDir = env_required('UPLOAD_DIR');
    $dt = new \DateTimeImmutable('now');
    $rel = sprintf('%s/%s/%s.%s', $dt->format('Y'), $dt->format('m'), $validated['sha256'], $validated['ext']);
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
