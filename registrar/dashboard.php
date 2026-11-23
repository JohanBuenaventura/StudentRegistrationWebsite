<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('admin', 'registrar');

$metrics = [
    'total_apps'      => 0,
    'submitted'       => 0,
    'under_review'    => 0,
    'correction'      => 0,
    'approved'        => 0,
    'rejected'        => 0,
    'total_students'  => 0,
];
$recentApps = [];
$recentStudents = [];

try {
    $appRow = $pdo->query(
        "SELECT
            COUNT(*) AS total_apps,
            SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) AS submitted,
            SUM(CASE WHEN status = 'Under Review' THEN 1 ELSE 0 END) AS under_review,
            SUM(CASE WHEN status = 'Requires Correction' THEN 1 ELSE 0 END) AS correction,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
         FROM applications"
    )->fetch();

    if ($appRow) {
        foreach (['total_apps', 'submitted', 'under_review', 'correction', 'approved', 'rejected'] as $k) {
            $metrics[$k] = (int)$appRow[$k];
        }
    }

    $metrics['total_students'] = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();

    $recentApps = $pdo->query(
        "SELECT a.id, a.reference_no, a.full_name, a.classification, a.status, a.submitted_at, a.updated_at,
                c.code AS course_code
         FROM applications a
         LEFT JOIN courses c ON a.course_id = c.id
         WHERE a.status != 'Draft'
         ORDER BY a.updated_at DESC, a.id DESC
         LIMIT 8"
    )->fetchAll();

    $recentStudents = $pdo->query(
        'SELECT id, student_no, full_name, course, campus, status, registration_date
         FROM students
         ORDER BY registration_date DESC, id DESC
         LIMIT 5'
    )->fetchAll();
} catch (PDOException $e) {
    set_flash('error', 'Unable to load dashboard metrics. Ensure migration 001 has been applied.');
}

render_header('Registrar Dashboard', 'registrar_dashboard');
?>

<section class="stats-grid" aria-label="Application and enrollment metrics">
    <article class="stat-card">
        <span class="stat-label">Total Applications</span>
        <strong class="stat-value"><?= $metrics['total_apps'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Submitted (Pending)</span>
        <strong class="stat-value"><?= $metrics['submitted'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Under Review</span>
        <strong class="stat-value"><?= $metrics['under_review'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Requires Correction</span>
        <strong class="stat-value"><?= $metrics['correction'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Approved</span>
        <strong class="stat-value"><?= $metrics['approved'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Rejected</span>
        <strong class="stat-value"><?= $metrics['rejected'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Official Students</span>
        <strong class="stat-value"><?= $metrics['total_students'] ?></strong>
    </article>
</section>

<section class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <div>
            <h1>Recent Applications Queue</h1>
            <p class="subtitle">Latest submitted and active online registration applications.</p>
        </div>
        <div class="header-actions">
            <a href="applications.php" class="btn btn-primary btn-sm">View All Applications</a>
            <a href="../index.php" class="btn btn-secondary btn-sm">+ Direct Register</a>
        </div>
    </div>

    <?php if (count($recentApps) === 0): ?>
        <div class="empty-state">
            <p>No submitted applications yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Reference No.</th>
                        <th>Applicant</th>
                        <th>Type</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentApps as $app): ?>
                        <tr>
                            <td><code><?= e($app['reference_no']) ?></code></td>
                            <td><strong><?= e($app['full_name']) ?></strong></td>
                            <td><?= e($app['classification']) ?></td>
                            <td><?= e($app['course_code'] ?? '—') ?></td>
                            <td>
                                <span class="badge <?= e(app_status_badge_class($app['status'])) ?>">
                                    <?= e($app['status']) ?>
                                </span>
                            </td>
                            <td><?= $app['submitted_at'] ? e(date('M j, Y', strtotime($app['submitted_at']))) : '—' ?></td>
                            <td>
                                <a href="review_application.php?id=<?= (int)$app['id'] ?>" class="btn btn-edit btn-sm">Review</a>
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
            <h2>Recent Official Registrations</h2>
            <p class="subtitle">Most recently enrolled or updated official student records.</p>
        </div>
        <a href="../view_students.php" class="btn btn-secondary btn-sm">Full Directory &rarr;</a>
    </div>

    <?php if (count($recentStudents) === 0): ?>
        <div class="empty-state">
            <p>No official student records yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Full Name</th>
                        <th>Program</th>
                        <th>Campus</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentStudents as $st): ?>
                        <tr>
                            <td><code><?= e($st['student_no'] ?: ('#' . $st['id'])) ?></code></td>
                            <td><strong><?= e($st['full_name']) ?></strong></td>
                            <td><?= e($st['course']) ?></td>
                            <td><?= e($st['campus']) ?></td>
                            <td><span class="badge badge-<?= e(strtolower($st['status'])) ?>"><?= e($st['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
