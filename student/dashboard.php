<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('student');

$user = current_user();
$userId = (int)$user['id'];

$officialStudent = null;
$applications = [];
$unreadCount = 0;

try {
    if (!empty($user['student_id'])) {
        $stStmt = $pdo->prepare(
            'SELECT s.student_no, s.full_name, s.course, s.year_level, s.status, s.campus, s.school_year, s.semester
             FROM students s
             WHERE s.id = :id
             LIMIT 1'
        );
        $stStmt->execute(['id' => (int)$user['student_id']]);
        $officialStudent = $stStmt->fetch() ?: null;
    }

    $appStmt = $pdo->prepare(
        'SELECT a.id, a.reference_no, a.classification, a.school_year, a.semester, a.campus,
                a.status, a.registrar_remarks, a.submitted_at, a.updated_at,
                c.code AS course_code, c.name AS course_name
         FROM applications a
         LEFT JOIN courses c ON a.course_id = c.id
         WHERE a.user_id = :user_id
         ORDER BY a.updated_at DESC, a.id DESC'
    );
    $appStmt->execute(['user_id' => $userId]);
    $applications = $appStmt->fetchAll();

    $notifStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
    );
    $notifStmt->execute(['user_id' => $userId]);
    $unreadCount = (int)$notifStmt->fetchColumn();
} catch (PDOException $e) {
    set_flash('error', 'Unable to load your portal data. Ensure database migration 001 has been applied.');
}

render_header('Student Dashboard', 'student_dashboard');
?>

<section class="stats-grid" aria-label="Student portal summary">
    <article class="stat-card">
        <span class="stat-label">My Applications</span>
        <strong class="stat-value"><?= count($applications) ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Official Student No.</span>
        <strong class="stat-value" style="font-size:1.25rem;">
            <?= $officialStudent ? e($officialStudent['student_no'] ?? 'Assigned') : 'Pending Approval' ?>
        </strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Enrollment Status</span>
        <strong class="stat-value" style="font-size:1.25rem;">
            <?= $officialStudent ? e($officialStudent['status']) : 'Not Yet Enrolled' ?>
        </strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Unread Notices</span>
        <strong class="stat-value"><?= $unreadCount ?></strong>
    </article>
</section>

<?php if ($officialStudent): ?>
    <section class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <div>
                <h2>Official WMSU Student Record</h2>
                <p class="subtitle">
                    <?= e(($officialStudent['course'] ?? '') . ' · Year ' . (int)$officialStudent['year_level'] . ' · ' . ($officialStudent['campus'] ?? '')) ?>
                    (<?= e(($officialStudent['school_year'] ?? '') . ' · ' . ($officialStudent['semester'] ?? '')) ?>)
                </p>
            </div>
            <span class="badge badge-active"><?= e($officialStudent['status']) ?></span>
        </div>
    </section>
<?php endif; ?>

<section class="card">
    <div class="card-header">
        <div>
            <h1>My Registration Applications</h1>
            <p class="subtitle">Start a new registration application, resume a draft, or check registrar remarks.</p>
        </div>
        <div class="header-actions">
            <a href="apply.php" class="btn btn-primary btn-sm">+ New Registration</a>
            <a href="requirements.php" class="btn btn-secondary btn-sm">Upload Requirements</a>
            <a href="profile.php" class="btn btn-secondary btn-sm">My Profile</a>
        </div>
    </div>

    <?php if (count($applications) === 0): ?>
        <div class="empty-state">
            <p>You have not started any registration applications yet.</p>
            <a href="apply.php" class="btn btn-primary">Start Online Registration</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Reference No.</th>
                        <th>Type</th>
                        <th>Program</th>
                        <th>Term &amp; Campus</th>
                        <th>Status</th>
                        <th>Registrar Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <?php
                        $programLabel = $app['course_code']
                            ? $app['course_code'] . ' — ' . $app['course_name']
                            : 'Not selected';
                        $canEdit = in_array($app['status'], ['Draft', 'Requires Correction'], true);
                        ?>
                        <tr>
                            <td><code><?= e($app['reference_no']) ?></code></td>
                            <td><?= e($app['classification']) ?></td>
                            <td><?= e($programLabel) ?></td>
                            <td><?= e($app['school_year'] . ' · ' . $app['semester'] . ' (' . $app['campus'] . ')') ?></td>
                            <td>
                                <span class="badge <?= e(app_status_badge_class($app['status'])) ?>">
                                    <?= e($app['status']) ?>
                                </span>
                            </td>
                            <td><?= $app['registrar_remarks'] ? e($app['registrar_remarks']) : '<span class="text-muted-link">None</span>' ?></td>
                            <td class="actions-cell">
                                <a href="track.php?id=<?= (int)$app['id'] ?>" class="btn btn-secondary btn-sm">Track</a>
                                <?php if ($canEdit): ?>
                                    <a href="apply.php?id=<?= (int)$app['id'] ?>" class="btn btn-edit btn-sm">
                                        <?= $app['status'] === 'Draft' ? 'Continue Draft' : 'Revise' ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
