<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/layout.php';

require_role('admin', 'registrar');

$courses = fetch_courses($pdo);
$statuses = allowed_statuses();
$campuses = allowed_campuses();
$semesters = allowed_semesters();

$formData = [
    'student_no'  => '',
    'full_name'   => '',
    'email'       => '',
    'course_id'   => '',
    'year_level'  => '1',
    'status'      => 'Active',
    'campus'      => 'Main - Baliwasan',
    'school_year' => '2026-2027',
    'semester'    => '1st Semester',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please refresh and try again.';
    }

    $formData['student_no']  = trim($_POST['student_no'] ?? '');
    $formData['full_name']   = trim($_POST['full_name'] ?? '');
    $formData['email']       = trim($_POST['email'] ?? '');
    $formData['course_id']   = trim($_POST['course_id'] ?? '');
    $formData['year_level']  = trim($_POST['year_level'] ?? '1');
    $formData['status']      = trim($_POST['status'] ?? 'Active');
    $formData['campus']      = trim($_POST['campus'] ?? 'Main - Baliwasan');
    $formData['school_year'] = trim($_POST['school_year'] ?? '2026-2027');
    $formData['semester']    = trim($_POST['semester'] ?? '1st Semester');

    if ($formData['student_no'] === '' || !preg_match('/^[A-Za-z0-9\-]{3,30}$/', $formData['student_no'])) {
        $errors[] = 'Student number is required (3-30 letters, numbers, or hyphens).';
    }
    if ($formData['full_name'] === '' || strlen($formData['full_name']) > 100) {
        $errors[] = 'Full name is required (max 100 characters).';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || strlen($formData['email']) > 100) {
        $errors[] = 'Please provide a valid email address (max 100 characters).';
    }

    $selectedCourse = null;
    foreach ($courses as $course) {
        if ((string)$course['id'] === $formData['course_id']) {
            $selectedCourse = $course;
            break;
        }
    }
    if (!$selectedCourse) {
        $errors[] = 'Please select a valid course.';
    }

    $yearLevel = filter_var($formData['year_level'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 5],
    ]);
    if ($yearLevel === false) {
        $errors[] = 'Year level must be between 1 and 5.';
    }

    if (!in_array($formData['status'], $statuses, true)) {
        $errors[] = 'Invalid enrollment status selected.';
    }
    if (!in_array($formData['campus'], $campuses, true)) {
        $errors[] = 'Invalid WMSU campus selected.';
    }
    if (!preg_match('/^\d{4}-\d{4}$/', $formData['school_year'])) {
        $errors[] = 'School year must be in YYYY-YYYY format (e.g. 2026-2027).';
    }
    if (!in_array($formData['semester'], $semesters, true)) {
        $errors[] = 'Invalid semester selected.';
    }

    if (empty($errors)) {
        try {
            $dupStmt = $pdo->prepare(
                'SELECT student_no, email
                 FROM students
                 WHERE email = :email OR student_no = :student_no
                 LIMIT 1'
            );
            $dupStmt->execute([
                'email'      => $formData['email'],
                'student_no' => $formData['student_no'],
            ]);
            $existing = $dupStmt->fetch();

            if ($existing) {
                if (strcasecmp($existing['student_no'], $formData['student_no']) === 0) {
                    $errors[] = 'A student with that student number already exists.';
                }
                if (strcasecmp($existing['email'], $formData['email']) === 0) {
                    $errors[] = 'A student with that email address already exists.';
                }
            } else {
                $insertStmt = $pdo->prepare(
                    'INSERT INTO students (student_no, full_name, email, course, course_id, year_level, status, campus, school_year, semester)
                     VALUES (:student_no, :full_name, :email, :course, :course_id, :year_level, :status, :campus, :school_year, :semester)'
                );
                $insertStmt->execute([
                    'student_no'  => $formData['student_no'],
                    'full_name'   => $formData['full_name'],
                    'email'       => $formData['email'],
                    'course'      => $selectedCourse['name'],
                    'course_id'   => (int)$selectedCourse['id'],
                    'year_level'  => (int)$yearLevel,
                    'status'      => $formData['status'],
                    'campus'      => $formData['campus'],
                    'school_year' => $formData['school_year'],
                    'semester'    => $formData['semester'],
                ]);

                $newId = (int)$pdo->lastInsertId();
                log_activity(
                    $pdo,
                    'CREATE_STUDENT',
                    'student',
                    $newId,
                    "Registered {$formData['full_name']} ({$formData['student_no']})"
                );

                set_flash('success', "Student {$formData['full_name']} ({$formData['student_no']}) registered successfully.");
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Unable to save student record. Please verify database schema.';
        }
    }
}

$stats = [
    'total'   => 0,
    'active'  => 0,
    'pending' => 0,
    'courses' => count($courses),
];
try {
    $statRow = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count
         FROM students"
    )->fetch();
    if ($statRow) {
        $stats['total']   = (int)$statRow['total'];
        $stats['active']  = (int)$statRow['active_count'];
        $stats['pending'] = (int)$statRow['pending_count'];
    }
} catch (PDOException $e) {
    // Keep zeroed stats if table not initialized yet.
}

render_header('Student Registration', 'register');
?>

<section class="stats-grid" aria-label="Registration summary">
    <article class="stat-card">
        <span class="stat-label">Total Students</span>
        <strong class="stat-value"><?= $stats['total'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Active Enrolled</span>
        <strong class="stat-value"><?= $stats['active'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Pending Review</span>
        <strong class="stat-value"><?= $stats['pending'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-label">Available Courses</span>
        <strong class="stat-value"><?= $stats['courses'] ?></strong>
    </article>
</section>

<section class="card card-medium">
    <div class="card-header">
        <div>
            <h1>Register New Student</h1>
            <p class="subtitle">Enter official student details to create an enrollment record.</p>
        </div>
        <a href="view_students.php" class="btn btn-secondary btn-sm">View Directory &rarr;</a>
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

    <form method="POST" action="index.php" class="form-grid" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="form-group">
            <label for="student_no">Student Number</label>
            <input
                type="text"
                id="student_no"
                name="student_no"
                placeholder="e.g. 2026-0001"
                value="<?= e($formData['student_no']) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                placeholder="e.g. Maria Clara Santos"
                value="<?= e($formData['full_name']) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="student@wmsu.edu.ph"
                value="<?= e($formData['email']) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="course_id">Course / Program</label>
            <select id="course_id" name="course_id" required>
                <option value="">Select a course...</option>
                <?php foreach ($courses as $course): ?>
                    <option
                        value="<?= (int)$course['id'] ?>"
                        <?= (string)$course['id'] === $formData['course_id'] ? 'selected' : '' ?>
                    >
                        <?= e($course['code'] . ' — ' . $course['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="year_level">Year Level</label>
            <select id="year_level" name="year_level" required>
                <?php for ($y = 1; $y <= 5; $y++): ?>
                    <option value="<?= $y ?>" <?= (string)$y === $formData['year_level'] ? 'selected' : '' ?>>
                        Year <?= $y ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Enrollment Status</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= e($st) ?>" <?= $st === $formData['status'] ? 'selected' : '' ?>>
                        <?= e($st) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="campus">WMSU Campus</label>
            <select id="campus" name="campus" required>
                <?php foreach ($campuses as $campusOption): ?>
                    <option value="<?= e($campusOption) ?>" <?= $campusOption === $formData['campus'] ? 'selected' : '' ?>>
                        <?= e($campusOption) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="school_year">School Year</label>
            <input
                type="text"
                id="school_year"
                name="school_year"
                placeholder="e.g. 2026-2027"
                value="<?= e($formData['school_year']) ?>"
                required
            >
        </div>

        <div class="form-group form-full">
            <label for="semester">Semester</label>
            <select id="semester" name="semester" required>
                <?php foreach ($semesters as $sem): ?>
                    <option value="<?= e($sem) ?>" <?= $sem === $formData['semester'] ? 'selected' : '' ?>>
                        <?= e($sem) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions form-full">
            <button type="submit" class="btn btn-primary">Register Student</button>
        </div>
    </form>
</section>

<?php render_footer(); ?>
