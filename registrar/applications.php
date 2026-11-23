<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('admin', 'registrar');

$courses = fetch_courses($pdo);
$statuses = allowed_application_statuses();
$campuses = allowed_campuses();

$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$courseFilter = trim($_GET['course_id'] ?? '');
$campusFilter = trim($_GET['campus'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(a.reference_no LIKE :q1 OR a.full_name LIKE :q2 OR a.email LIKE :q3 OR a.student_no LIKE :q4)';
    $like = '%' . $search . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
    $where[] = 'a.status = :status';
    $params['status'] = $statusFilter;
}
if ($courseFilter !== '' && ctype_digit($courseFilter)) {
    $where[] = 'a.course_id = :course_id';
    $params['course_id'] = (int)$courseFilter;
}
if ($campusFilter !== '' && in_array($campusFilter, $campuses, true)) {
    $where[] = 'a.campus = :campus';
    $params['campus'] = $campusFilter;
}

$sql = 'SELECT a.id, a.reference_no, a.classification, a.student_no, a.full_name, a.email,
               a.school_year, a.semester, a.campus, a.year_level, a.status, a.submitted_at,
               c.code AS course_code, c.name AS course_name
        FROM applications a
        LEFT JOIN courses c ON a.course_id = c.id';

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY FIELD(a.status, "Submitted", "Under Review", "Requires Correction", "Approved", "Rejected", "Draft"), a.updated_at DESC';

$applications = [];
$queryError = '';
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
} catch (PDOException $e) {
    $queryError = 'Unable to load applications. Verify migration 001 has been applied.';
}

render_header('Applications Queue', 'registrar_applications');
?>

<section class="card">
    <div class="card-header">
        <div>
            <h1>Online Applications</h1>
            <p class="subtitle">Showing <?= count($applications) ?> application<?= count($applications) === 1 ? '' : 's' ?>.</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Dashboard</a>
    </div>

    <form method="GET" action="applications.php" class="filter-bar">
        <div class="filter-field filter-search">
            <label for="q" class="sr-only">Search applications</label>
            <input type="search" id="q" name="q" placeholder="Search reference no., name, email, or student no..." value="<?= e($search) ?>">
        </div>

        <div class="filter-field">
            <label for="status" class="sr-only">Filter by status</label>
            <select id="status" name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= e($st) ?>" <?= $st === $statusFilter ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="course_id" class="sr-only">Filter by course</label>
            <select id="course_id" name="course_id">
                <option value="">All Courses</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int)$course['id'] ?>" <?= (string)$course['id'] === $courseFilter ? 'selected' : '' ?>>
                        <?= e($course['code'] . ' — ' . $course['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="campus" class="sr-only">Filter by campus</label>
            <select id="campus" name="campus">
                <option value="">All Campuses</option>
                <?php foreach ($campuses as $c): ?>
                    <option value="<?= e($c) ?>" <?= $c === $campusFilter ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($search !== '' || $statusFilter !== '' || $courseFilter !== '' || $campusFilter !== ''): ?>
                <a href="applications.php" class="btn btn-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($queryError !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($queryError) ?></div>
    <?php elseif (count($applications) === 0): ?>
        <div class="empty-state">
            <p>No applications match your current filters.</p>
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
                        <th>Term &amp; Campus</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><code><?= e($app['reference_no']) ?></code></td>
                            <td>
                                <strong><?= e($app['full_name']) ?></strong><br>
                                <small class="text-muted-link"><?= e($app['email']) ?></small>
                            </td>
                            <td><?= e($app['classification']) ?></td>
                            <td><?= e($app['course_code'] ?? '—') ?> (Yr <?= (int)$app['year_level'] ?>)</td>
                            <td><?= e($app['school_year'] . ' · ' . $app['semester'] . ' · ' . $app['campus']) ?></td>
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

<?php render_footer(); ?>
