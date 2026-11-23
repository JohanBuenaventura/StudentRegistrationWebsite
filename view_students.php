<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/layout.php';

require_role('admin', 'registrar');

$courses = fetch_courses($pdo);
$statuses = allowed_statuses();
$campuses = allowed_campuses();

$queryInfo = build_student_filter_query($_GET);
$search = $queryInfo['filters']['q'];
$courseFilter = $queryInfo['filters']['course_id'];
$statusFilter = $queryInfo['filters']['status'];
$campusFilter = $queryInfo['filters']['campus'];

$exportParams = array_filter($queryInfo['filters'], static fn(string $v): bool => $v !== '');
$exportUrl = 'export_students.php' . (!empty($exportParams) ? '?' . http_build_query($exportParams) : '');

$students = [];
$queryError = '';
try {
    $stmt = $pdo->prepare($queryInfo['sql']);
    $stmt->execute($queryInfo['params']);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $queryError = 'Unable to load student records. Verify that schema.sql has been applied.';
}

render_header('Student Directory', 'students');
?>

<section class="card">
    <div class="card-header">
        <div>
            <h1>Registered Students</h1>
            <p class="subtitle">
                Showing <?= count($students) ?> record<?= count($students) === 1 ? '' : 's' ?>.
            </p>
        </div>
        <div class="header-actions">
            <?php if (is_logged_in()): ?>
                <a href="<?= e($exportUrl) ?>" class="btn btn-secondary btn-sm">Export CSV</a>
                <a href="index.php" class="btn btn-primary btn-sm">+ Register Student</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-secondary btn-sm">Sign in to manage records</a>
            <?php endif; ?>
        </div>
    </div>

    <form method="GET" action="view_students.php" class="filter-bar">
        <div class="filter-field filter-search">
            <label for="q" class="sr-only">Search students</label>
            <input
                type="search"
                id="q"
                name="q"
                placeholder="Search by name, student no., or email..."
                value="<?= e($search) ?>"
            >
        </div>

        <div class="filter-field">
            <label for="course_id" class="sr-only">Filter by course</label>
            <select id="course_id" name="course_id">
                <option value="">All Courses</option>
                <?php foreach ($courses as $course): ?>
                    <option
                        value="<?= (int)$course['id'] ?>"
                        <?= (string)$course['id'] === $courseFilter ? 'selected' : '' ?>
                    >
                        <?= e($course['code'] . ' — ' . $course['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="status" class="sr-only">Filter by status</label>
            <select id="status" name="status">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= e($st) ?>" <?= $st === $statusFilter ? 'selected' : '' ?>>
                        <?= e($st) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="campus" class="sr-only">Filter by campus</label>
            <select id="campus" name="campus">
                <option value="">All Campuses</option>
                <?php foreach ($campuses as $campusOption): ?>
                    <option value="<?= e($campusOption) ?>" <?= $campusOption === $campusFilter ? 'selected' : '' ?>>
                        <?= e($campusOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($search !== '' || $courseFilter !== '' || $statusFilter !== '' || $campusFilter !== ''): ?>
                <a href="view_students.php" class="btn btn-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($queryError !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($queryError) ?></div>
    <?php elseif (count($students) === 0): ?>
        <div class="empty-state">
            <p>No student records match your current criteria.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Campus</th>
                        <th>Year</th>
                        <th>Term</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $statusClass = 'badge-' . strtolower($student['status']);
                        $courseLabel = $student['course_code']
                            ? $student['course_code'] . ' — ' . $student['course']
                            : $student['course'];
                        $termLabel = ($student['school_year'] ?? '2026-2027') . ' · ' . ($student['semester'] ?? '1st Semester');
                        ?>
                        <tr>
                            <td><code><?= e($student['student_no'] ?: ('#' . $student['id'])) ?></code></td>
                            <td><strong><?= e($student['full_name']) ?></strong></td>
                            <td><?= e($student['email']) ?></td>
                            <td><?= e($courseLabel) ?></td>
                            <td><?= e($student['campus'] ?? 'Main - Baliwasan') ?></td>
                            <td>Year <?= (int)$student['year_level'] ?></td>
                            <td><?= e($termLabel) ?></td>
                            <td>
                                <span class="badge <?= e($statusClass) ?>">
                                    <?= e($student['status']) ?>
                                </span>
                            </td>
                            <td class="actions-cell no-print">
                                <?php if (is_logged_in()): ?>
                                    <a href="edit_student.php?id=<?= (int)$student['id'] ?>" class="btn btn-edit btn-sm">Edit</a>
                                    <form
                                        method="POST"
                                        action="delete_student.php"
                                        class="inline-form"
                                        onsubmit="return confirm('Delete <?= e($student['full_name']) ?>? This cannot be undone.');"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <a href="login.php" class="text-muted-link">Sign in</a>
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
