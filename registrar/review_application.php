<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/documents_helper.php';

require_role('admin', 'registrar');

$reviewer = current_user();
$reviewerId = (int)$reviewer['id'];

$appId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
}

if (!$appId || $appId <= 0) {
    set_flash('error', 'Invalid application ID.');
    header('Location: applications.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT a.*, c.code AS course_code, c.name AS course_name,
            u.username AS applicant_username, u.student_id AS user_student_id,
            s.student_no AS official_student_no
     FROM applications a
     LEFT JOIN courses c ON a.course_id = c.id
     LEFT JOIN users u ON a.user_id = u.id
     LEFT JOIN students s ON a.approved_student_id = s.id
     WHERE a.id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $appId]);
$application = $stmt->fetch();

if (!$application) {
    set_flash('error', 'Application not found.');
    header('Location: applications.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please refresh and try again.';
    }

    if (empty($errors) && ($_POST['form_type'] ?? '') === 'document_review') {
        $docId = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
        $docDecision = $_POST['doc_decision'] ?? '';
        $docRemarks = trim($_POST['rejection_remarks'] ?? '');

        if (!$docId || $docId <= 0 || !in_array($docDecision, ['Verified', 'Rejected'], true)) {
            $errors[] = 'Invalid document verification request.';
        } elseif ($docDecision === 'Rejected' && $docRemarks === '') {
            $errors[] = 'Please provide rejection remarks when rejecting a document.';
        } else {
            try {
                $docLookup = $pdo->prepare('SELECT id, original_filename FROM documents WHERE id = :id AND application_id = :app_id LIMIT 1');
                $docLookup->execute(['id' => $docId, 'app_id' => $appId]);
                $docRow = $docLookup->fetch();

                if (!$docRow) {
                    $errors[] = 'Document not found for this application.';
                } else {
                    $updDoc = $pdo->prepare(
                        'UPDATE documents
                         SET verification_status = :status,
                             rejection_remarks = :remarks,
                             verified_by = :verified_by,
                             verified_at = NOW()
                         WHERE id = :id'
                    );
                    $updDoc->execute([
                        'status'      => $docDecision,
                        'remarks'     => $docDecision === 'Rejected' ? substr($docRemarks, 0, 255) : null,
                        'verified_by' => $reviewerId,
                        'id'          => $docId,
                    ]);

                    if ($docDecision === 'Rejected') {
                        $nStmt = $pdo->prepare(
                            'INSERT INTO notifications (user_id, application_id, title, message)
                             VALUES (:user_id, :application_id, :title, :message)'
                        );
                        $nStmt->execute([
                            'user_id'        => (int)$application['user_id'],
                            'application_id' => $appId,
                            'title'          => 'Document Requires Replacement',
                            'message'        => substr("Document '{$docRow['original_filename']}' was rejected: {$docRemarks}", 0, 255),
                        ]);
                    }

                    log_activity(
                        $pdo,
                        $docDecision === 'Verified' ? 'VERIFY_DOCUMENT' : 'REJECT_DOCUMENT',
                        'document',
                        $docId,
                        "{$docDecision} document '{$docRow['original_filename']}' for {$application['reference_no']}"
                    );

                    set_flash('success', "Document marked as {$docDecision}.");
                    header('Location: review_application.php?id=' . $appId);
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = 'Unable to update document status.';
            }
        }
    } elseif (empty($errors)) {
        $decision = $_POST['decision'] ?? '';
    $registrarRemarks = trim($_POST['registrar_remarks'] ?? '');
    $internalNotes = trim($_POST['internal_notes'] ?? '');
    $assignedStudentNo = trim($_POST['assigned_student_no'] ?? '');

    $allowedDecisions = ['under_review', 'request_correction', 'approve', 'reject'];
    if (!in_array($decision, $allowedDecisions, true)) {
        $errors[] = 'Invalid review action.';
    }

    if ($application['status'] === 'Draft') {
        $errors[] = 'Draft applications cannot be reviewed until submitted by the student.';
    }
    if ($application['status'] === 'Approved') {
        $errors[] = 'This application has already been approved.';
    }
    if (in_array($decision, ['request_correction', 'reject'], true) && $registrarRemarks === '') {
        $errors[] = 'Registrar remarks are required when requesting corrections or rejecting an application.';
    }

    if (empty($errors)) {
        $oldStatus = $application['status'];
        $newStatus = match ($decision) {
            'under_review'       => 'Under Review',
            'request_correction' => 'Requires Correction',
            'approve'            => 'Approved',
            'reject'             => 'Rejected',
        };

        try {
            $pdo->beginTransaction();

            $approvedStudentId = $application['approved_student_id'] ? (int)$application['approved_student_id'] : null;

            if ($decision === 'approve') {
                if (!$application['course_id'] || !$application['course_name']) {
                    throw new RuntimeException('Cannot approve an application without a valid course.');
                }

                // Look for an existing official student record (Returning student or matching email/student_no)
                $lookupNo = $assignedStudentNo !== '' ? $assignedStudentNo : ($application['student_no'] ?? '');
                $existingStudent = null;

                if ($lookupNo !== '') {
                    $stCheck = $pdo->prepare('SELECT * FROM students WHERE student_no = :student_no LIMIT 1');
                    $stCheck->execute(['student_no' => $lookupNo]);
                    $existingStudent = $stCheck->fetch() ?: null;
                }

                if (!$existingStudent) {
                    $emCheck = $pdo->prepare('SELECT * FROM students WHERE email = :email LIMIT 1');
                    $emCheck->execute(['email' => $application['email']]);
                    $existingStudent = $emCheck->fetch() ?: null;
                }

                if ($application['classification'] === 'Returning' && $lookupNo !== '' && !$existingStudent) {
                    throw new RuntimeException("Returning student number '{$lookupNo}' was not found in official student records.");
                }

                if ($existingStudent) {
                    $approvedStudentId = (int)$existingStudent['id'];
                    $updStudent = $pdo->prepare(
                        "UPDATE students
                         SET full_name = :full_name,
                             course = :course,
                             course_id = :course_id,
                             year_level = :year_level,
                             status = 'Active',
                             campus = :campus,
                             school_year = :school_year,
                             semester = :semester
                         WHERE id = :id"
                    );
                    $updStudent->execute([
                        'full_name'   => $application['full_name'],
                        'course'      => $application['course_name'],
                        'course_id'   => (int)$application['course_id'],
                        'year_level'  => (int)$application['year_level'],
                        'campus'      => $application['campus'],
                        'school_year' => $application['school_year'],
                        'semester'    => $application['semester'],
                        'id'          => $approvedStudentId,
                    ]);
                } else {
                    $finalStudentNo = $lookupNo !== ''
                        ? $lookupNo
                        : (date('Y') . '-' . strtoupper(bin2hex(random_bytes(2))));

                    $dupNo = $pdo->prepare('SELECT id FROM students WHERE student_no = :student_no LIMIT 1');
                    $dupNo->execute(['student_no' => $finalStudentNo]);
                    if ($dupNo->fetch()) {
                        throw new RuntimeException("Student number '{$finalStudentNo}' already exists.");
                    }

                    $insStudent = $pdo->prepare(
                        "INSERT INTO students
                            (student_no, full_name, email, course, course_id, year_level, status, campus, school_year, semester)
                         VALUES
                            (:student_no, :full_name, :email, :course, :course_id, :year_level, 'Active', :campus, :school_year, :semester)"
                    );
                    $insStudent->execute([
                        'student_no'  => $finalStudentNo,
                        'full_name'   => $application['full_name'],
                        'email'       => $application['email'],
                        'course'      => $application['course_name'],
                        'course_id'   => (int)$application['course_id'],
                        'year_level'  => (int)$application['year_level'],
                        'campus'      => $application['campus'],
                        'school_year' => $application['school_year'],
                        'semester'    => $application['semester'],
                    ]);
                    $approvedStudentId = (int)$pdo->lastInsertId();
                }

                // Link user account to official student record
                $linkUser = $pdo->prepare('UPDATE users SET student_id = :student_id WHERE id = :user_id');
                $linkUser->execute([
                    'student_id' => $approvedStudentId,
                    'user_id'    => (int)$application['user_id'],
                ]);
            }

            $updApp = $pdo->prepare(
                'UPDATE applications
                 SET status = :status,
                     registrar_remarks = :registrar_remarks,
                     internal_notes = :internal_notes,
                     reviewed_by = :reviewed_by,
                     reviewed_at = NOW(),
                     approved_student_id = :approved_student_id
                 WHERE id = :id'
            );
            $updApp->execute([
                'status'              => $newStatus,
                'registrar_remarks'   => $registrarRemarks !== '' ? $registrarRemarks : null,
                'internal_notes'      => $internalNotes !== '' ? $internalNotes : null,
                'reviewed_by'         => $reviewerId,
                'approved_student_id' => $approvedStudentId,
                'id'                  => $appId,
            ]);

            $histStmt = $pdo->prepare(
                'INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks)
                 VALUES (:application_id, :old_status, :new_status, :changed_by, :remarks)'
            );
            $histStmt->execute([
                'application_id' => $appId,
                'old_status'     => $oldStatus,
                'new_status'     => $newStatus,
                'changed_by'     => $reviewerId,
                'remarks'        => $registrarRemarks !== '' ? substr($registrarRemarks, 0, 255) : "Status updated to {$newStatus}",
            ]);

            $notifTitle = "Application {$newStatus}";
            $notifMsg = "Your application ({$application['reference_no']}) is now {$newStatus}."
                . ($registrarRemarks !== '' ? ' Remarks: ' . $registrarRemarks : '');
            $notifStmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, application_id, title, message)
                 VALUES (:user_id, :application_id, :title, :message)'
            );
            $notifStmt->execute([
                'user_id'        => (int)$application['user_id'],
                'application_id' => $appId,
                'title'          => substr($notifTitle, 0, 120),
                'message'        => substr($notifMsg, 0, 255),
            ]);

            $pdo->commit();

            log_activity(
                $pdo,
                $decision === 'approve' ? 'APPROVE_APPLICATION' : 'REVIEW_APPLICATION',
                'application',
                $appId,
                "Updated {$application['reference_no']} from {$oldStatus} to {$newStatus}"
            );

            set_flash('success', "Application {$application['reference_no']} updated to {$newStatus}.");
            header('Location: review_application.php?id=' . $appId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to process application review.';
        }
    }
    }
}

$gStmt = $pdo->prepare('SELECT * FROM application_guardians WHERE application_id = :id LIMIT 1');
$gStmt->execute(['id' => $appId]);
$guardian = $gStmt->fetch() ?: null;

$pStmt = $pdo->prepare('SELECT * FROM application_previous_schools WHERE application_id = :id LIMIT 1');
$pStmt->execute(['id' => $appId]);
$prevSchool = $pStmt->fetch() ?: null;

$hStmt = $pdo->prepare(
    'SELECT h.old_status, h.new_status, h.remarks, h.created_at, u.full_name AS actor_name
     FROM application_status_history h
     LEFT JOIN users u ON h.changed_by = u.id
     WHERE h.application_id = :id
     ORDER BY h.created_at DESC, h.id DESC'
);
$hStmt->execute(['id' => $appId]);
$history = $hStmt->fetchAll();

$dStmt = $pdo->prepare(
    'SELECT d.*, r.name AS requirement_name
     FROM documents d
     LEFT JOIN registration_requirements r ON d.requirement_id = r.id
     WHERE d.application_id = :id
     ORDER BY d.uploaded_at DESC, d.id DESC'
);
$dStmt->execute(['id' => $appId]);
$documents = $dStmt->fetchAll();

render_header('Review Application', 'registrar_applications');
?>

<section class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <div>
            <h1>Review Application <code><?= e($application['reference_no']) ?></code></h1>
            <p class="subtitle">
                Applicant: <strong><?= e($application['full_name']) ?></strong> (@<?= e($application['applicant_username']) ?>)
                &middot; <?= e($application['classification']) ?>
            </p>
        </div>
        <div class="header-actions">
            <span class="badge <?= e(app_status_badge_class($application['status'])) ?>">
                <?= e($application['status']) ?>
            </span>
            <a href="applications.php" class="btn btn-secondary btn-sm">&larr; Applications</a>
        </div>
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

    <div class="review-grid">
        <div class="review-item">
            <span class="review-label">Email &amp; Contact</span>
            <strong><?= e($application['email'] . ' · ' . ($application['contact_no'] ?: 'N/A')) ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">DOB &amp; Sex</span>
            <strong><?= e(($application['date_of_birth'] ?: 'N/A') . ' · ' . ($application['sex'] ?: 'N/A')) ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Address</span>
            <strong><?= e($application['address'] ?: 'N/A') ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Program &amp; Year</span>
            <strong><?= e(($application['course_code'] ? $application['course_code'] . ' — ' : '') . ($application['course_name'] ?? 'N/A') . ' (Year ' . (int)$application['year_level'] . ')') ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Campus &amp; Term</span>
            <strong><?= e($application['campus'] . ' · ' . $application['school_year'] . ' (' . $application['semester'] . ')') ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Applicant / Official Student No.</span>
            <strong><?= e($application['official_student_no'] ?: ($application['student_no'] ?: 'To be assigned on approval')) ?></strong>
        </div>
        <?php if ($guardian): ?>
            <div class="review-item">
                <span class="review-label">Guardian</span>
                <strong><?= e($guardian['full_name'] . ' (' . $guardian['relationship'] . ') · ' . $guardian['contact_no']) ?></strong>
            </div>
        <?php endif; ?>
        <?php if ($prevSchool): ?>
            <div class="review-item">
                <span class="review-label">Previous School</span>
                <strong><?= e($prevSchool['school_name'] . ($prevSchool['last_grade_or_year'] ? ' · ' . $prevSchool['last_grade_or_year'] : '')) ?></strong>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (!in_array($application['status'], ['Draft', 'Approved'], true)): ?>
    <section class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <div>
                <h2>Registrar Decision</h2>
                <p class="subtitle">Approving creates or updates the official student record atomically.</p>
            </div>
        </div>

        <form method="POST" action="review_application.php" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="application_id" value="<?= (int)$application['id'] ?>">

            <div class="form-group">
                <label for="assigned_student_no">Assign / Confirm Student Number (Optional for New)</label>
                <input
                    type="text"
                    id="assigned_student_no"
                    name="assigned_student_no"
                    placeholder="Auto-generated if left blank"
                    value="<?= e($application['student_no'] ?? '') ?>"
                >
            </div>

            <div class="form-group">
                <label for="internal_notes">Internal Staff Notes (Hidden from Student)</label>
                <input
                    type="text"
                    id="internal_notes"
                    name="internal_notes"
                    value="<?= e($application['internal_notes'] ?? '') ?>"
                >
            </div>

            <div class="form-group form-full">
                <label for="registrar_remarks">Registrar Remarks (Visible to Student; required for Correction / Reject)</label>
                <input
                    type="text"
                    id="registrar_remarks"
                    name="registrar_remarks"
                    value="<?= e($application['registrar_remarks'] ?? '') ?>"
                >
            </div>

            <div class="form-actions form-full">
                <button type="submit" name="decision" value="under_review" class="btn btn-secondary">Mark Under Review</button>
                <button type="submit" name="decision" value="request_correction" class="btn btn-secondary">Request Correction</button>
                <button type="submit" name="decision" value="approve" class="btn btn-edit" onclick="return confirm('Approve this application and enroll student?');">Approve &amp; Enroll</button>
                <button type="submit" name="decision" value="reject" class="btn btn-danger" onclick="return confirm('Reject this application?');">Reject</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <div>
            <h2>Uploaded Requirements &amp; Verification</h2>
            <p class="subtitle">Preview, download, verify, or reject submitted applicant documents.</p>
        </div>
    </div>

    <?php if (empty($documents)): ?>
        <div class="empty-state"><p>No documents uploaded for this application yet.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Requirement</th>
                        <th>File</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Verify / Reject</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td><strong><?= e($doc['requirement_name'] ?? 'General Document') ?></strong></td>
                            <td>
                                <?= e($doc['original_filename']) ?><br>
                                <a href="../download_document.php?id=<?= (int)$doc['id'] ?>&amp;mode=preview" target="_blank" class="text-muted-link">Preview</a> &middot;
                                <a href="../download_document.php?id=<?= (int)$doc['id'] ?>" class="text-muted-link">Download</a>
                            </td>
                            <td><?= number_format((int)$doc['file_size'] / 1024, 1) ?> KB</td>
                            <td>
                                <span class="badge <?= e(doc_status_badge_class($doc['verification_status'])) ?>">
                                    <?= e($doc['verification_status']) ?>
                                </span>
                                <?php if (!empty($doc['rejection_remarks'])): ?>
                                    <br><small class="text-muted-link"><?= e($doc['rejection_remarks']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="review_application.php" class="doc-verify-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="application_id" value="<?= (int)$appId ?>">
                                    <input type="hidden" name="form_type" value="document_review">
                                    <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
                                    <input type="text" name="rejection_remarks" placeholder="Remarks if rejecting..." value="<?= e($doc['rejection_remarks'] ?? '') ?>" class="doc-remark-input">
                                    <button type="submit" name="doc_decision" value="Verified" class="btn btn-edit btn-sm">Verify</button>
                                    <button type="submit" name="doc_decision" value="Rejected" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Status History</h2>
            <p class="subtitle">Audit trail of all status changes for this application.</p>
        </div>
    </div>

    <?php if (count($history) === 0): ?>
        <div class="empty-state"><p>No status history recorded yet.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Transition</th>
                        <th>Actor</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <tr>
                            <td><?= e(date('M j, Y g:i A', strtotime($item['created_at']))) ?></td>
                            <td>
                                <?php if ($item['old_status']): ?>
                                    <code><?= e($item['old_status']) ?></code> &rarr;
                                <?php endif; ?>
                                <span class="badge <?= e(app_status_badge_class($item['new_status'])) ?>">
                                    <?= e($item['new_status']) ?>
                                </span>
                            </td>
                            <td><?= e($item['actor_name'] ?? 'System') ?></td>
                            <td><?= e($item['remarks'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
