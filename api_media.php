<?php
/**
 * api_media.php — Media Library API for Prakut CMS.
 * Handles safe file uploads, image previews, and asset management.
 * Modeled after Zoloverse architecture.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? $_REQUEST['action'] ?? '';

if (($method === 'POST' || $method === 'GET') && $action === 'delete') {
    $method = 'DELETE';
}
if ($method === 'POST' && ($_POST['_method'] ?? '') === 'DELETE') {
    $method = 'DELETE';
}
if ($method === 'POST') {
    $dataCheck = read_json_body();
    if ($dataCheck && !empty($dataCheck->action) && $dataCheck->action === 'delete') {
        $method = 'DELETE';
    }
}

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        json_error('Method Not Allowed', 405);
}

function handlePost(): void
{
    $admin = requireAuth(true);

    if (empty($_FILES['file'])) {
        json_error('No file was uploaded.');
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error('File upload failed with error code: ' . $file['error']);
    }

    // Limit size to 10MB
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        json_error('File exceeds maximum allowed size of 10 MB.');
    }

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'application/pdf' => 'pdf'
    ];

    if (!isset($allowedMimes[$mime])) {
        json_error('Only JPG, PNG, WEBP, GIF, and PDF files are allowed.');
    }

    $ext = $allowedMimes[$mime];
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = sprintf('asset_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
    $destPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        json_error('Failed to move uploaded file to destination.', 500);
    }

    $relativePath = 'uploads/' . $filename;

    logAudit('UPLOAD_MEDIA', "Uploaded media asset: {$filename} ({$file['size']} bytes)", (int)$admin->id);

    json_success('File uploaded successfully.', [
        'filename'      => $filename,
        'path'          => $relativePath,
        'url'           => $relativePath,
        'mime_type'     => $mime,
        'size'          => $file['size'],
    ], 201);
}

function handleGet(): void
{
    $admin = requireAuth(true);
    $uploadDir = __DIR__ . '/uploads';
    $files = [];

    if (is_dir($uploadDir)) {
        $scan = scandir($uploadDir);
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..' || $f === '.gitignore') continue;
            $full = $uploadDir . '/' . $f;
            if (is_file($full)) {
                $files[] = [
                    'filename'   => $f,
                    'path'       => 'uploads/' . $f,
                    'size'       => filesize($full),
                    'created_at' => date('Y-m-d H:i:s', filemtime($full))
                ];
            }
        }
    }

    // Sort newest first
    usort($files, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

    json_success('Media library retrieved.', ['files' => $files, 'total' => count($files)]);
}

function handleDelete(): void
{
    $admin = requireAuth(true);
    $filename = trim((string)($_GET['filename'] ?? $_POST['filename'] ?? ''));

    if (empty($filename)) {
        $data = read_json_body();
        if ($data && !empty($data->filename)) {
            $filename = trim((string)$data->filename);
        }
    }

    $filename = basename($filename); // prevent directory traversal
    $target = __DIR__ . '/uploads/' . $filename;

    if (!is_file($target)) {
        json_error('File not found in media library.', 404);
    }

    if (!unlink($target)) {
        json_error('Failed to delete file.', 500);
    }

    logAudit('DELETE_MEDIA', "Deleted media file: {$filename}", (int)$admin->id);
    json_success('File deleted successfully from media library.');
}
