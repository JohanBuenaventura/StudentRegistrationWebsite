<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/layout.php';

require_login();

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editingCourse = null;

if ($editId && $editId > 0) {
    $editStmt = $pdo->prepare('SELECT id, code, name, department FROM courses WHERE id = :id LIMIT 1');
    $editStmt->execute(['id' => $editId]);
    $editingCourse = $editStmt->fetch() ?: null;
    if (!$editingCourse) {
        set_flash('error', 'Course not found.');
        header('Location: courses.php');
        exit;
    }
}

$formData = [
    'code'       => $editingCourse['code'] ?? '',
    'name'       => $editingCourse['name'] ?? '',
    'department' => $editingCourse['department'] ?? '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid session token. Please refresh and try again.');
        header('Location: courses.php');
        exit;
    }

    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $deleteId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$deleteId || $deleteId <= 0) {
            set_flash('error', 'Invalid course ID.');
            header('Location: courses.php');
            exit;
        }

        try {
            $courseStmt = $pdo->prepare('SELECT id, code, name FROM courses WHERE id = :id LIMIT 1');
            $courseStmt->execute(['id' => $deleteId]);
            $course = $courseStmt->fetch();

            if (!$course) {
                set_flash('error', 'Course not found.');
                header('Location: courses.php');
                exit;
            }

            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM students WHERE course_id = :id');
            $countStmt->execute(['id' => $deleteId]);
            $enrolledCount = (int)$countStmt->fetchColumn();

            if ($enrolledCount > 0) {
                set_flash(
                    'error',
                    "Cannot delete {$course['code']} ({$course['name']}): {$enrolledCount} student(s) are still enrolled."
                );
                header('Location: courses.php');
                exit;
            }

            $delStmt = $pdo->prepare('DELETE FROM courses WHERE id = :id');
            $delStmt->execute(['id' => $deleteId]);

            log_activity(
                $pdo,
                'DELETE_COURSE',
                'course',
                $deleteId,
                "Deleted course {$course['code']} — {$course['name']}"
            );

            set_flash('success', "Course {$course['code']} deleted.");
        } catch (PDOException $e) {
            set_flash('error', 'Unable to delete course.');
        }

        header('Location: courses.php');
        exit;
    }

    $targetId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $isUpdate = ($action === 'update' && $targetId && $targetId > 0);
    if ($isUpdate) {
        $editingCourse = ['id' => $targetId];
    }

    $formData['code']       = strtoupper(trim($_POST['code'] ?? ''));
    $formData['name']       = trim($_POST['name'] ?? '');
    $formData['department'] = trim($_POST['department'] ?? '');

    if ($formData['code'] === '' || !preg_match('/^[A-Za-z0-9\-]{2,20}$/', $formData['code'])) {
        $errors[] = 'Course code is required (2-20 letters, numbers, or hyphens).';
    }
    if ($formData['name'] === '' || strlen($formData['name']) > 120) {
        $errors[] = 'Course name is required (max 120 characters).';
    }
    if ($formData['department'] === '' || strlen($formData['department']) > 100) {
        $errors[] = 'Department is required (max 100 characters).';
    }

    if (empty($errors)) {
        try {
            if ($isUpdate) {
                $dupStmt = $pdo->prepare(
                    'SELECT id FROM courses WHERE (code = :code OR name = :name) AND id != :id LIMIT 1'
                );
                $dupStmt->execute([
                    'code' => $formData['code'],
                    'name' => $formData['name'],
                    'id'   => $targetId,
                ]);
                if ($dupStmt->fetch()) {
                    $errors[] = 'Another course already uses that code or name.';
                } else {
                    $updateStmt = $pdo->prepare(
                        'UPDATE courses SET code = :code, name = :name, department = :department WHERE id = :id'
                    );
                    $updateStmt->execute([
                        'code'       => $formData['code'],
                        'name'       => $formData['name'],
                        'department' => $formData['department'],
                        'id'         => $targetId,
                    ]);

                    $syncStudents = $pdo->prepare('UPDATE students SET course = :name WHERE course_id = :id');
                    $syncStudents->execute([
                        'name' => $formData['name'],
                        'id'   => $targetId,
                    ]);

                    log_activity(
                        $pdo,
                        'UPDATE_COURSE',
                        'course',
                        $targetId,
                        "Updated course {$formData['code']} — {$formData['name']}"
                    );

                    set_flash('success', "Course {$formData['code']} updated.");
                    header('Location: courses.php');
                    exit;
                }
            } else {
                $dupStmt = $pdo->prepare(
                    'SELECT code, name FROM courses WHERE code = :code OR name = :name LIMIT 1'
                );
                $dupStmt->execute([
                    'code' => $formData['code'],
                    'name' => $formData['name'],
                ]);
                if ($dupStmt->fetch()) {
                    $errors[] = 'A course with that code or name already exists.';
                } else {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO courses (code, name, department)
                         VALUES (:code, :name, :department)'
                    );
                    $insertStmt->execute([
                        'code'       => $formData['code'],
                        'name'       => $formData['name'],
                        'department' => $formData['department'],
                    ]);

                    $newId = (int)$pdo->lastInsertId();
                    log_activity(
                        $pdo,
                        'CREATE_COURSE',
                        'course',
                        $newId,
                        "Added course {$formData['code']} — {$formData['name']}"
                    );

                    set_flash('success', "Course {$formData['code']} — {$formData['name']} added.");
                    header('Location: courses.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Unable to save course.';
        }
    }
}

$courses = [];
try {
    $courses = $pdo->query(
        'SELECT c.id, c.code, c.name, c.department, COUNT(s.id) AS student_count
         FROM courses c
         LEFT JOIN students s ON s.course_id = c.id
         GROUP BY c.id, c.code, c.name, c.department
         ORDER BY c.name ASC'
    )->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'Unable to load courses.';
}

render_header('Courses', 'courses');
?>

<div class="split-layout">
    <section class="card">
        <div class="card-header">
            <div>
                <h1><?= $editingCourse ? 'Edit Course' : 'Add Course' ?></h1>
                <p class="subtitle">
                    <?= $editingCourse ? 'Update WMSU program details.' : 'Create a degree program for student enrollment.' ?>
                </p>
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

        <form method="POST" action="courses.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editingCourse ? 'update' : 'create' ?>">
            <?php if ($editingCourse): ?>
                <input type="hidden" name="id" value="<?= (int)$editingCourse['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="code">Course Code</label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    placeholder="e.g. BSCS"
                    value="<?= e($formData['code']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="name">Course Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="e.g. BS Computer Science"
                    value="<?= e($formData['name']) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="department">Department</label>
                <input
                    type="text"
                    id="department"
                    name="department"
                    placeholder="e.g. College of Computing Studies"
                    value="<?= e($formData['department']) ?>"
                    required
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    <?= $editingCourse ? 'Save Course' : 'Add Course' ?>
                </button>
                <?php if ($editingCourse): ?>
                    <a href="courses.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h2>Available Courses</h2>
                <p class="subtitle"><?= count($courses) ?> program<?= count($courses) === 1 ? '' : 's' ?> configured.</p>
            </div>
        </div>

        <?php if (count($courses) === 0): ?>
            <div class="empty-state">
                <p>No courses added yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Program Name</th>
                            <th>Department</th>
                            <th>Enrolled</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><code><?= e($course['code']) ?></code></td>
                                <td><strong><?= e($course['name']) ?></strong></td>
                                <td><?= e($course['department']) ?></td>
                                <td><?= (int)$course['student_count'] ?></td>
                                <td class="actions-cell">
                                    <a href="courses.php?edit=<?= (int)$course['id'] ?>" class="btn btn-edit btn-sm">Edit</a>
                                    <form
                                        method="POST"
                                        action="courses.php"
                                        class="inline-form"
                                        onsubmit="return confirm('Delete course <?= e($course['code']) ?>?');"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$course['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php render_footer(); ?>
