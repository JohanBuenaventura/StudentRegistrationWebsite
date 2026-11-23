<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('student');

$user = current_user();
$userId = (int)$user['id'];
$appId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$appId || $appId <= 0) {
    set_flash('error', 'Invalid application ID.');
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT a.*, c.code AS course_code, c.name AS course_name,
            s.student_no AS official_student_no
     FROM applications a
     LEFT JOIN courses c ON a.course_id = c.id
     LEFT JOIN students s ON a.approved_student_id = s.id
     WHERE a.id = :id AND a.user_id = :user_id
     LIMIT 1'
);
$stmt->execute(['id' => $appId, 'user_id' => $userId]);
$application = $stmt->fetch();

if (!$application) {
    set_flash('error', 'Application not found or access denied.');
    header('Location: dashboard.php');
    exit;
}

$gStmt = $pdo->prepare('SELECT * FROM application_guardians WHERE application_id = :id LIMIT 1');
$gStmt->execute(['id' => $appId]);
$guardian = $gStmt->fetch() ?: null;

$pStmt = $pdo->prepare('SELECT * FROM application_previous_schools WHERE application_id = :id LIMIT 1');
$pStmt->execute(['id' => $appId]);
$prevSchool = $pStmt->fetch() ?: null;

$hStmt = $pdo->prepare(
    'SELECT h.old_status, h.new_status, h.remarks, h.created_at,
            u.full_name AS actor_name, u.role AS actor_role
     FROM application_status_history h
     LEFT JOIN users u ON h.changed_by = u.id
     WHERE h.application_id = :id
     ORDER BY h.created_at DESC, h.id DESC'
);
$hStmt->execute(['id' => $appId]);
$history = $hStmt->fetchAll();

render_header('Application Tracking', 'student_dashboard');
?>

<section class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <div>
            <h1>Application <code><?= e($application['reference_no']) ?></code></h1>
            <p class="subtitle">
                <?= e($application['classification']) ?> Applicant &middot;
                <?= e($application['school_year'] . ' · ' . $application['semester'] . ' · ' . $application['campus']) ?>
            </p>
        </div>
        <div class="header-actions">
            <span class="badge <?= e(app_status_badge_class($application['status'])) ?>">
                <?= e($application['status']) ?>
            </span>
            <?php if (in_array($application['status'], ['Draft', 'Requires Correction'], true)): ?>
                <a href="apply.php?id=<?= (int)$application['id'] ?>" class="btn btn-edit btn-sm">Edit Application</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Dashboard</a>
        </div>
    </div>

    <?php if (!empty($application['registrar_remarks'])): ?>
        <div class="alert <?= $application['status'] === 'Approved' ? 'alert-success' : 'alert-error' ?>">
            <strong>Registrar Remarks:</strong> <?= e($application['registrar_remarks']) ?>
        </div>
    <?php endif; ?>

    <div class="review-grid">
        <div class="review-item">
            <span class="review-label">Applicant Name</span>
            <strong><?= e($application['full_name']) ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Email</span>
            <strong><?= e($application['email']) ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Program</span>
            <strong><?= e(($application['course_code'] ? $application['course_code'] . ' — ' : '') . ($application['course_name'] ?? 'Not selected')) ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Year Level</span>
            <strong>Year <?= (int)$application['year_level'] ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Submitted At</span>
            <strong><?= $application['submitted_at'] ? e(date('M j, Y g:i A', strtotime($application['submitted_at']))) : 'Not yet submitted' ?></strong>
        </div>
        <div class="review-item">
            <span class="review-label">Official Student No.</span>
            <strong><?= $application['official_student_no'] ? e($application['official_student_no']) : 'Pending approval' ?></strong>
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
                <strong><?= e($prevSchool['school_name']) ?></strong>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <div class="card-header">
        <div>
            <h2>Status History</h2>
            <p class="subtitle">Chronological review updates for your application.</p>
        </div>
    </div>

    <?php if (count($history) === 0): ?>
        <div class="empty-state">
            <p>No status history yet. Submit your draft to begin registrar review.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Transition</th>
                        <th>Updated By</th>
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
                            <td><?= $item['actor_name'] ? e($item['actor_name'] . ' (' . $item['actor_role'] . ')') : 'System' ?></td>
                            <td><?= e($item['remarks'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
