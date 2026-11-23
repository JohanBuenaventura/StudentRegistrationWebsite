<?php
require_once __DIR__ . '/auth.php';

/**
 * Return the private upload directory outside the public web root.
 */
function document_storage_dir(): string
{
    $envDir = getenv('UPLOAD_DIR');
    if ($envDir !== false && trim($envDir) !== '') {
        return rtrim(trim($envDir), '/\\');
    }
    if (PHP_OS_FAMILY === 'Linux' && is_dir('/var/www')) {
        return '/var/www/wmsu_uploads';
    }
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'wmsu_uploads';
}

/**
 * Allowed server-verified MIME types mapped to safe extensions.
 */
function allowed_document_mimes(): array
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
    ];
}

/**
 * Maximum allowed upload size in bytes (5 MB).
 */
function max_document_bytes(): int
{
    return 5 * 1024 * 1024;
}

/**
 * Validate and store an uploaded requirement document securely.
 *
 * @return array{ok: bool, error: ?string, doc_id: ?int}
 */
function store_uploaded_document(PDO $pdo, array $file, int $applicationId, ?int $requirementId, int $userId): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Invalid file upload payload.', 'doc_id' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds the maximum allowed upload size.',
            UPLOAD_ERR_NO_FILE                        => 'Please choose a file to upload.',
            default                                   => 'File upload failed (error code ' . (int)$file['error'] . ').',
        };
        return ['ok' => false, 'error' => $msg, 'doc_id' => null];
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'error' => 'Invalid uploaded file source.', 'doc_id' => null];
    }

    $fileSize = (int)($file['size'] ?? 0);
    if ($fileSize <= 0 || $fileSize > max_document_bytes()) {
        return ['ok' => false, 'error' => 'File size must be between 1 byte and 5 MB.', 'doc_id' => null];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $mimeMap = allowed_document_mimes();
    if (!isset($mimeMap[$detectedMime])) {
        return ['ok' => false, 'error' => 'Only PDF, JPG, and PNG files are allowed.', 'doc_id' => null];
    }

    $storageDir = document_storage_dir();
    if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true) && !is_dir($storageDir)) {
        return ['ok' => false, 'error' => 'Upload storage directory is not available.', 'doc_id' => null];
    }

    $ext = $mimeMap[$detectedMime];
    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = $storageDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        return ['ok' => false, 'error' => 'Failed to save uploaded file to secure storage.', 'doc_id' => null];
    }

    $originalName = substr(basename((string)($file['name'] ?? ('document.' . $ext))), 0, 255);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO documents
                (application_id, requirement_id, uploaded_by, original_filename, stored_filename, mime_type, file_size, verification_status)
             VALUES
                (:application_id, :requirement_id, :uploaded_by, :original_filename, :stored_filename, :mime_type, :file_size, 'Pending')"
        );
        $stmt->execute([
            'application_id'    => $applicationId,
            'requirement_id'    => $requirementId,
            'uploaded_by'       => $userId,
            'original_filename' => $originalName,
            'stored_filename'   => $storedName,
            'mime_type'         => $detectedMime,
            'file_size'         => $fileSize,
        ]);

        $docId = (int)$pdo->lastInsertId();
        log_activity($pdo, 'UPLOAD_DOCUMENT', 'document', $docId, "Uploaded {$originalName} for application #{$applicationId}");
        return ['ok' => true, 'error' => null, 'doc_id' => $docId];
    } catch (PDOException $e) {
        if (is_file($destPath)) {
            @unlink($destPath);
        }
        return ['ok' => false, 'error' => 'Database error while recording uploaded document.', 'doc_id' => null];
    }
}

/**
 * Map document verification status to a CSS badge class.
 */
function doc_status_badge_class(string $status): string
{
    return match ($status) {
        'Verified' => 'badge-active',
        'Rejected' => 'badge-inactive',
        default    => 'badge-pending',
    };
}
