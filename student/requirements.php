<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/documents_helper.php';

require_role('student');

$user = current_user();
$userId = (int)$user['id'];

$appsStmt = $pdo->prepare(
    'SELECT id, reference_no, classification, status
     FROM applications
     WHERE user_id = :user_id
     ORDER BY updated_at DESC, id DESC'
);
$appsStmt->execute(['user_id' => $userId]);
$applications = $appsStmt->fetchAll();

$selectedAppId = filter_input(INPUT_GET, 'app_id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedAppId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
}
if (!$selectedAppId && !empty($applications)) {
    $selectedAppId = (int)$applications[0]['id'];
}

$selectedApp = null;
foreach ($applications as $app) {
    if ((int)$app['id'] === (int)$selectedAppId) {
        $selectedApp = $app;
        break;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please refresh and try again.';
    } elseif (!$selectedApp) {
        $errors[] = 'Application not found or access denied.';
    } elseif (in_array($selectedApp['status'], ['Approved', 'Rejected'], true)) {
        $errors[] = 'Documents cannot be uploaded for finalized applications.';
    } else {
        $reqId = filter_input(INPUT_POST, 'requirement_id', FILTER_VALIDATE_INT);
        $reqId = ($reqId && $reqId > 0) ? $reqId : null;

        if (!isset($_FILES['document_file'])) {
            $errors[] = 'Please select a file to upload.';
        } else {
            $result = store_uploaded_document($pdo, $_FILES['document_file'], (int)$selectedApp['id'], $reqId, $userId);
            if ($result['ok']) {
                set_flash('success', 'Document uploaded and queued for registrar verification.');
                header('Location: requirements.php?app_id=' . (int)$selectedApp['id']);
                exit;
            }
            $errors[] = $result['error'] ?? 'Upload failed.';
        }
    }
}

$requirements = [];
$uploadedDocs = [];

if ($selectedApp) {
    $reqStmt = $pdo->prepare(
        "SELECT id, code, name, description, applies_to, is_required
         FROM registration_requirements
         WHERE is_active = 1 AND (applies_to = 'All' OR applies_to = :classification)
         ORDER BY is_required DESC, name ASC"
    );
    $reqStmt->execute(['classification' => $selectedApp['classification']]);
    $requirements = $reqStmt->fetchAll();

    $docStmt = $pdo->prepare(
        'SELECT d.*, r.name AS requirement_name
         FROM documents d
         LEFT JOIN registration_requirements r ON d.requirement_id = r.id
         WHERE d.application_id = :app_id
         ORDER BY d.uploaded_at DESC, d.id DESC'
    );
    $docStmt->execute(['app_id' => (int)$selectedApp['id']]);
    $uploadedDocs = $docStmt->fetchAll();
}

render_header('My Requirements', 'student_requirements');
?>

<section class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <div>
            <h1>My Requirements &amp; Uploads</h1>
            <p class="subtitle">Upload PDF, JPG, or PNG requirements (max 5 MB per file).</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Dashboard</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
            <ul class="error-list">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($applications)): ?>
        <div class="empty-state">
            <p>Start a registration application first before uploading requirements.</p>
            <a href="apply.php" class="btn btn-primary">Start Online Registration</a>
        </div>
    <?php else: ?>
        <form method="GET" action="requirements.php" class="filter-bar">
            <div class="filter-field filter-search">
                <label for="app_id" class="sr-only">Select Application</label>
                <select id="app_id" name="app_id" onchange="this.form.submit()">
                    <?php foreach ($applications as $app): ?>
                        <option value="<?= (int)$app['id'] ?>" <?= (int)$app['id'] === (int)$selectedApp['id'] ? 'selected' : '' ?>>
                            <?= e($app['reference_no'] . ' — ' . $app['classification'] . ' (' . $app['status'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if (!in_array($selectedApp['status'], ['Approved', 'Rejected'], true)): ?>
            <form method="POST" action="requirements.php" enctype="multipart/form-data" class="form-grid" style="margin-bottom:1.5rem;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="application_id" value="<?= (int)$selectedApp['id'] ?>">

                <div class="form-group">
                    <label for="requirement_id">Requirement Type</label>
                    <select id="requirement_id" name="requirement_id" required>
                        <option value="">Select requirement...</option>
                        <?php foreach ($requirements as $req): ?>
                            <option value="<?= (int)$req['id'] ?>">
                                <?= e($req['name'] . ((int)$req['is_required'] ? ' (Required)' : ' (Optional)')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="document_file">Document File (PDF, JPG, PNG · max 5 MB)</label>
                    <input type="file" id="document_file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>

                <div class="form-actions form-full">
                    <button type="submit" class="btn btn-primary">Upload Requirement</button>
                </div>
            </form>
        <?php endif; ?>

        <h2 class="section-subtitle">Uploaded Documents</h2>
        <?php if (empty($uploadedDocs)): ?>
            <div class="empty-state">
                <p>No documents uploaded for this application yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Requirement</th>
                            <th>File</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Registrar Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uploadedDocs as $doc): ?>
                            <tr>
                                <td><strong><?= e($doc['requirement_name'] ?? 'General Document') ?></strong></td>
                                <td><?= e($doc['original_filename']) ?></td>
                                <td><?= number_format((int)$doc['file_size'] / 1024, 1) ?> KB</td>
                                <td>
                                    <span class="badge <?= e(doc_status_badge_class($doc['verification_status'])) ?>">
                                        <?= e($doc['verification_status']) ?>
                                    </span>
                                </td>
                                <td><?= $doc['rejection_remarks'] ? e($doc['rejection_remarks']) : '<span class="text-muted-link">None</span>' ?></td>
                                <td class="actions-cell">
                                    <a href="../download_document.php?id=<?= (int)$doc['id'] ?>&amp;mode=preview" target="_blank" class="btn btn-secondary btn-sm">Preview</a>
                                    <a href="../download_document.php?id=<?= (int)$doc['id'] ?>" class="btn btn-edit btn-sm">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
