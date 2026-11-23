<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/documents_helper.php';

require_login();

$docId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$docId || $docId <= 0) {
    http_response_code(400);
    exit('Invalid document ID.');
}

$stmt = $pdo->prepare(
    'SELECT d.*, a.user_id AS owner_user_id
     FROM documents d
     INNER JOIN applications a ON d.application_id = a.id
     WHERE d.id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

$user = current_user();
$isStaff = has_role('admin', 'registrar');
$isOwner = has_role('student') && (int)$doc['owner_user_id'] === (int)$user['id'];

if (!$isStaff && !$isOwner) {
    http_response_code(403);
    exit('Access denied.');
}

$safeStored = basename((string)$doc['stored_filename']);
$filePath = document_storage_dir() . DIRECTORY_SEPARATOR . $safeStored;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('Document file is missing from storage.');
}

$mimeMap = allowed_document_mimes();
$mimeType = isset($mimeMap[$doc['mime_type']]) ? $doc['mime_type'] : 'application/octet-stream';
$mode = ($_GET['mode'] ?? 'download') === 'preview' ? 'inline' : 'attachment';
$downloadName = preg_replace('/[^A-Za-z0-9._\-]/', '_', basename((string)$doc['original_filename']));

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: ' . $mode . '; filename="' . $downloadName . '"');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($filePath);
exit;
