<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('student');

$user = current_user();
$userId = (int)$user['id'];

$courses = fetch_courses($pdo);
$campuses = allowed_campuses();
$semesters = allowed_semesters();
$classifications = ['New', 'Returning', 'Transferee'];
$sexOptions = ['Male', 'Female', 'Prefer not to say'];

// Configurable registration validation rules
$validationRules = [
    'require_dob_on_submit'             => true,
    'require_contact_on_submit'         => true,
    'require_address_on_submit'         => true,
    'require_guardian_on_submit'        => true,
    'require_previous_school_on_submit' => true,
    'require_student_no_for_returning'  => true,
];

$activeTerm = ['label' => '2026-2027', 'semester' => '1st Semester'];
$requirements = [];
try {
    $termRow = $pdo->query(
        'SELECT label, semester FROM school_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1'
    )->fetch();
    if ($termRow) {
        $activeTerm = $termRow;
    }

    $requirements = $pdo->query(
        'SELECT id, code, name, description, applies_to, is_required
         FROM registration_requirements
         WHERE is_active = 1
         ORDER BY is_required DESC, name ASC'
    )->fetchAll();
} catch (PDOException $e) {
    // Fallback to defaults if migration tables are missing
}

$appId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
}

$existingApp = null;
$existingGuardian = null;
$existingPrevSchool = null;

if ($appId && $appId > 0) {
    $appStmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id AND user_id = :user_id LIMIT 1');
    $appStmt->execute(['id' => $appId, 'user_id' => $userId]);
    $existingApp = $appStmt->fetch() ?: null;

    if (!$existingApp) {
        set_flash('error', 'Application not found or access denied.');
        header('Location: dashboard.php');
        exit;
    }
    if (!in_array($existingApp['status'], ['Draft', 'Requires Correction'], true)) {
        set_flash('error', 'Submitted or finalized applications cannot be edited.');
        header('Location: dashboard.php');
        exit;
    }

    $gStmt = $pdo->prepare('SELECT * FROM application_guardians WHERE application_id = :id LIMIT 1');
    $gStmt->execute(['id' => $appId]);
    $existingGuardian = $gStmt->fetch() ?: null;

    $pStmt = $pdo->prepare('SELECT * FROM application_previous_schools WHERE application_id = :id LIMIT 1');
    $pStmt->execute(['id' => $appId]);
    $existingPrevSchool = $pStmt->fetch() ?: null;
}

$formData = [
    'classification'        => $existingApp['classification'] ?? 'New',
    'student_no'            => $existingApp['student_no'] ?? '',
    'full_name'             => $existingApp['full_name'] ?? ($user['full_name'] ?? ''),
    'date_of_birth'         => $existingApp['date_of_birth'] ?? '',
    'sex'                   => $existingApp['sex'] ?? 'Male',
    'contact_no'            => $existingApp['contact_no'] ?? '',
    'email'                 => $existingApp['email'] ?? ($user['email'] ?? ''),
    'address'               => $existingApp['address'] ?? '',
    'school_year'           => $existingApp['school_year'] ?? $activeTerm['label'],
    'semester'              => $existingApp['semester'] ?? $activeTerm['semester'],
    'campus'                => $existingApp['campus'] ?? 'Main - Baliwasan',
    'course_id'             => (string)($existingApp['course_id'] ?? ''),
    'year_level'            => (string)($existingApp['year_level'] ?? '1'),
    'guardian_name'         => $existingGuardian['full_name'] ?? '',
    'guardian_relationship' => $existingGuardian['relationship'] ?? '',
    'guardian_contact'      => $existingGuardian['contact_no'] ?? '',
    'guardian_address'      => $existingGuardian['address'] ?? '',
    'prev_school_name'      => $existingPrevSchool['school_name'] ?? '',
    'prev_grade_level'      => $existingPrevSchool['last_grade_or_year'] ?? '',
    'prev_track_program'    => $existingPrevSchool['academic_track_or_program'] ?? '',
    'prev_year_graduated'   => $existingPrevSchool['year_graduated'] ?? '',
    'prev_gwa'              => $existingPrevSchool['gwa'] ?? '',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please refresh and try again.';
    }

    $submitAction = ($_POST['submit_action'] ?? 'draft') === 'submit' ? 'submit' : 'draft';
    $isSubmitting = ($submitAction === 'submit');

    foreach (array_keys($formData) as $field) {
        $formData[$field] = trim((string)($_POST[$field] ?? ''));
    }

    if ($formData['full_name'] === '' || strlen($formData['full_name']) > 100) {
        $errors[] = 'Full name is required (max 100 characters).';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || strlen($formData['email']) > 100) {
        $errors[] = 'Please provide a valid email address (max 100 characters).';
    }
    if (!in_array($formData['classification'], $classifications, true)) {
        $errors[] = 'Invalid student classification.';
    }
    if (!in_array($formData['campus'], $campuses, true)) {
        $errors[] = 'Invalid WMSU campus.';
    }
    if (!in_array($formData['semester'], $semesters, true)) {
        $errors[] = 'Invalid semester.';
    }
    if (!preg_match('/^\d{4}-\d{4}$/', $formData['school_year'])) {
        $errors[] = 'School year must be in YYYY-YYYY format.';
    }

    $yearLevel = filter_var($formData['year_level'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 5],
    ]);
    if ($yearLevel === false) {
        $errors[] = 'Year level must be between 1 and 5.';
    }

    $selectedCourseId = null;
    if ($formData['course_id'] !== '') {
        foreach ($courses as $c) {
            if ((string)$c['id'] === $formData['course_id']) {
                $selectedCourseId = (int)$c['id'];
                break;
            }
        }
        if ($selectedCourseId === null) {
            $errors[] = 'Selected course is invalid.';
        }
    }

    if ($formData['prev_gwa'] !== '' && (!is_numeric($formData['prev_gwa']) || (float)$formData['prev_gwa'] < 0 || (float)$formData['prev_gwa'] > 100)) {
        $errors[] = 'Previous GWA must be a valid number between 0 and 100.';
    }

    if ($isSubmitting) {
        if ($validationRules['require_dob_on_submit'] && $formData['date_of_birth'] === '') {
            $errors[] = 'Date of birth is required to submit.';
        }
        if ($formData['sex'] !== '' && !in_array($formData['sex'], $sexOptions, true)) {
            $errors[] = 'Invalid sex option selected.';
        }
        if ($validationRules['require_contact_on_submit'] && $formData['contact_no'] === '') {
            $errors[] = 'Contact number is required to submit.';
        }
        if ($validationRules['require_address_on_submit'] && $formData['address'] === '') {
            $errors[] = 'Complete address is required to submit.';
        }
        if ($selectedCourseId === null) {
            $errors[] = 'Please select a degree program / course.';
        }
        if ($validationRules['require_student_no_for_returning'] && $formData['classification'] === 'Returning' && $formData['student_no'] === '') {
            $errors[] = 'Returning students must provide their existing WMSU student number.';
        }
        if ($validationRules['require_guardian_on_submit']) {
            if ($formData['guardian_name'] === '' || $formData['guardian_relationship'] === '' || $formData['guardian_contact'] === '') {
                $errors[] = 'Parent/Guardian name, relationship, and contact number are required to submit.';
            }
        }
        if ($validationRules['require_previous_school_on_submit'] && $formData['prev_school_name'] === '') {
            $errors[] = 'Previous school name is required to submit.';
        }
        if (empty($_POST['confirm_submit'])) {
            $errors[] = 'Please confirm that all entered information is accurate before submitting.';
        }
    }

    if (empty($errors)) {
        $newStatus = $isSubmitting ? 'Submitted' : 'Draft';
        $oldStatus = $existingApp['status'] ?? null;

        try {
            $pdo->beginTransaction();

            if ($existingApp) {
                $targetAppId = (int)$existingApp['id'];
                $refNo = $existingApp['reference_no'];

                $updApp = $pdo->prepare(
                    'UPDATE applications
                     SET classification = :classification,
                         student_no = :student_no,
                         full_name = :full_name,
                         date_of_birth = :date_of_birth,
                         sex = :sex,
                         contact_no = :contact_no,
                         email = :email,
                         address = :address,
                         school_year = :school_year,
                         semester = :semester,
                         campus = :campus,
                         course_id = :course_id,
                         year_level = :year_level,
                         status = :status,
                         submitted_at = IF(:is_submit = 1, NOW(), submitted_at)
                     WHERE id = :id AND user_id = :user_id'
                );
                $updApp->execute([
                    'classification' => $formData['classification'],
                    'student_no'     => $formData['student_no'] !== '' ? $formData['student_no'] : null,
                    'full_name'      => $formData['full_name'],
                    'date_of_birth'  => $formData['date_of_birth'] !== '' ? $formData['date_of_birth'] : null,
                    'sex'            => in_array($formData['sex'], $sexOptions, true) ? $formData['sex'] : null,
                    'contact_no'     => $formData['contact_no'] !== '' ? $formData['contact_no'] : null,
                    'email'          => $formData['email'],
                    'address'        => $formData['address'] !== '' ? $formData['address'] : null,
                    'school_year'    => $formData['school_year'],
                    'semester'       => $formData['semester'],
                    'campus'         => $formData['campus'],
                    'course_id'      => $selectedCourseId,
                    'year_level'     => (int)$yearLevel,
                    'status'         => $newStatus,
                    'is_submit'      => $isSubmitting ? 1 : 0,
                    'id'             => $targetAppId,
                    'user_id'        => $userId,
                ]);
            } else {
                $refNo = 'WMSU-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $insApp = $pdo->prepare(
                    'INSERT INTO applications
                        (reference_no, user_id, classification, student_no, full_name, date_of_birth, sex,
                         contact_no, email, address, school_year, semester, campus, course_id, year_level,
                         status, submitted_at)
                     VALUES
                        (:reference_no, :user_id, :classification, :student_no, :full_name, :date_of_birth, :sex,
                         :contact_no, :email, :address, :school_year, :semester, :campus, :course_id, :year_level,
                         :status, IF(:is_submit = 1, NOW(), NULL))'
                );
                $insApp->execute([
                    'reference_no'   => $refNo,
                    'user_id'        => $userId,
                    'classification' => $formData['classification'],
                    'student_no'     => $formData['student_no'] !== '' ? $formData['student_no'] : null,
                    'full_name'      => $formData['full_name'],
                    'date_of_birth'  => $formData['date_of_birth'] !== '' ? $formData['date_of_birth'] : null,
                    'sex'            => in_array($formData['sex'], $sexOptions, true) ? $formData['sex'] : null,
                    'contact_no'     => $formData['contact_no'] !== '' ? $formData['contact_no'] : null,
                    'email'          => $formData['email'],
                    'address'        => $formData['address'] !== '' ? $formData['address'] : null,
                    'school_year'    => $formData['school_year'],
                    'semester'       => $formData['semester'],
                    'campus'         => $formData['campus'],
                    'course_id'      => $selectedCourseId,
                    'year_level'     => (int)$yearLevel,
                    'status'         => $newStatus,
                    'is_submit'      => $isSubmitting ? 1 : 0,
                ]);
                $targetAppId = (int)$pdo->lastInsertId();
            }

            if ($formData['guardian_name'] !== '' || $formData['guardian_contact'] !== '') {
                $gUpsert = $pdo->prepare(
                    'INSERT INTO application_guardians (application_id, full_name, relationship, contact_no, address)
                     VALUES (:application_id, :full_name, :relationship, :contact_no, :address)
                     ON DUPLICATE KEY UPDATE
                        full_name = VALUES(full_name),
                        relationship = VALUES(relationship),
                        contact_no = VALUES(contact_no),
                        address = VALUES(address)'
                );
                $gUpsert->execute([
                    'application_id' => $targetAppId,
                    'full_name'      => $formData['guardian_name'] !== '' ? $formData['guardian_name'] : 'Not specified',
                    'relationship'   => $formData['guardian_relationship'] !== '' ? $formData['guardian_relationship'] : 'Guardian',
                    'contact_no'     => $formData['guardian_contact'] !== '' ? $formData['guardian_contact'] : 'N/A',
                    'address'        => $formData['guardian_address'] !== '' ? $formData['guardian_address'] : null,
                ]);
            }

            if ($formData['prev_school_name'] !== '') {
                $pUpsert = $pdo->prepare(
                    'INSERT INTO application_previous_schools
                        (application_id, school_name, last_grade_or_year, academic_track_or_program, year_graduated, gwa)
                     VALUES
                        (:application_id, :school_name, :last_grade_or_year, :academic_track_or_program, :year_graduated, :gwa)
                     ON DUPLICATE KEY UPDATE
                        school_name = VALUES(school_name),
                        last_grade_or_year = VALUES(last_grade_or_year),
                        academic_track_or_program = VALUES(academic_track_or_program),
                        year_graduated = VALUES(year_graduated),
                        gwa = VALUES(gwa)'
                );
                $pUpsert->execute([
                    'application_id'            => $targetAppId,
                    'school_name'               => $formData['prev_school_name'],
                    'last_grade_or_year'        => $formData['prev_grade_level'] !== '' ? $formData['prev_grade_level'] : null,
                    'academic_track_or_program' => $formData['prev_track_program'] !== '' ? $formData['prev_track_program'] : null,
                    'year_graduated'            => $formData['prev_year_graduated'] !== '' ? $formData['prev_year_graduated'] : null,
                    'gwa'                       => $formData['prev_gwa'] !== '' ? (float)$formData['prev_gwa'] : null,
                ]);
            }

            if ($isSubmitting) {
                $histStmt = $pdo->prepare(
                    'INSERT INTO application_status_history (application_id, old_status, new_status, changed_by, remarks)
                     VALUES (:application_id, :old_status, :new_status, :changed_by, :remarks)'
                );
                $histStmt->execute([
                    'application_id' => $targetAppId,
                    'old_status'     => $oldStatus,
                    'new_status'     => 'Submitted',
                    'changed_by'     => $userId,
                    'remarks'        => 'Application submitted by student.',
                ]);

                $notifStmt = $pdo->prepare(
                    'INSERT INTO notifications (user_id, application_id, title, message)
                     VALUES (:user_id, :application_id, :title, :message)'
                );
                $notifStmt->execute([
                    'user_id'        => $userId,
                    'application_id' => $targetAppId,
                    'title'          => 'Application Submitted',
                    'message'        => "Your registration application ({$refNo}) has been submitted for review.",
                ]);
            }

            $pdo->commit();

            log_activity(
                $pdo,
                $isSubmitting ? 'SUBMIT_APPLICATION' : 'SAVE_APPLICATION_DRAFT',
                'application',
                $targetAppId,
                ($isSubmitting ? 'Submitted ' : 'Saved draft ') . $refNo
            );

            if ($isSubmitting) {
                set_flash('success', "Application {$refNo} submitted successfully! You can track its progress from your dashboard.");
                header('Location: dashboard.php');
                exit;
            }

            set_flash('success', "Draft {$refNo} saved.");
            header('Location: apply.php?id=' . $targetAppId);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to save application. Please verify all fields and database tables.';
        }
    }
}

render_header('Online Registration', 'student_apply');
?>

<section class="card">
    <div class="card-header">
        <div>
            <h1>WMSU Online Student Registration</h1>
            <p class="subtitle">
                <?= $existingApp ? 'Editing Reference: <code>' . e($existingApp['reference_no']) . '</code> (' . e($existingApp['status']) . ')' : 'Complete all 6 steps or save a draft at any time.' ?>
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Back to Dashboard</a>
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

    <div class="wizard-steps" role="tablist" aria-label="Registration steps">
        <button type="button" class="wizard-step active" data-step="1">1. Personal</button>
        <button type="button" class="wizard-step" data-step="2">2. Academic</button>
        <button type="button" class="wizard-step" data-step="3">3. Guardian</button>
        <button type="button" class="wizard-step" data-step="4">4. Prev. School</button>
        <button type="button" class="wizard-step" data-step="5">5. Requirements</button>
        <button type="button" class="wizard-step" data-step="6">6. Review &amp; Submit</button>
    </div>

    <form method="POST" action="apply.php" id="applicationForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php if ($existingApp): ?>
            <input type="hidden" name="application_id" value="<?= (int)$existingApp['id'] ?>">
        <?php endif; ?>

        <!-- Step 1: Personal Information -->
        <div class="wizard-panel active" data-panel="1">
            <h2 class="section-subtitle">Step 1: Personal Information</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= e($formData['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?= e($formData['date_of_birth']) ?>">
                </div>
                <div class="form-group">
                    <label for="sex">Sex</label>
                    <select id="sex" name="sex">
                        <?php foreach ($sexOptions as $opt): ?>
                            <option value="<?= e($opt) ?>" <?= $opt === $formData['sex'] ? 'selected' : '' ?>><?= e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="contact_no">Contact Number</label>
                    <input type="text" id="contact_no" name="contact_no" placeholder="e.g. 09171234567" value="<?= e($formData['contact_no']) ?>">
                </div>
                <div class="form-group form-full">
                    <label for="address">Complete Address</label>
                    <input type="text" id="address" name="address" placeholder="Street, Barangay, City/Municipality, Province" value="<?= e($formData['address']) ?>">
                </div>
            </div>
        </div>

        <!-- Step 2: Academic Information -->
        <div class="wizard-panel" data-panel="2">
            <h2 class="section-subtitle">Step 2: Academic Information</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label for="classification">Student Classification *</label>
                    <select id="classification" name="classification" required>
                        <?php foreach ($classifications as $cls): ?>
                            <option value="<?= e($cls) ?>" <?= $cls === $formData['classification'] ? 'selected' : '' ?>><?= e($cls) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="studentNoGroup">
                    <label for="student_no">Existing Student Number (Returning Students)</label>
                    <input type="text" id="student_no" name="student_no" placeholder="e.g. 2025-0012" value="<?= e($formData['student_no']) ?>">
                </div>
                <div class="form-group">
                    <label for="school_year">School Year</label>
                    <input type="text" id="school_year" name="school_year" value="<?= e($formData['school_year']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" required>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= e($sem) ?>" <?= $sem === $formData['semester'] ? 'selected' : '' ?>><?= e($sem) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="campus">WMSU Campus</label>
                    <select id="campus" name="campus" required>
                        <?php foreach ($campuses as $c): ?>
                            <option value="<?= e($c) ?>" <?= $c === $formData['campus'] ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="course_id">Course / Program *</label>
                    <select id="course_id" name="course_id">
                        <option value="">Select a course...</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= (int)$course['id'] ?>" <?= (string)$course['id'] === $formData['course_id'] ? 'selected' : '' ?>>
                                <?= e($course['code'] . ' — ' . $course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="year_level">Year Level</label>
                    <select id="year_level" name="year_level">
                        <?php for ($y = 1; $y <= 5; $y++): ?>
                            <option value="<?= $y ?>" <?= (string)$y === $formData['year_level'] ? 'selected' : '' ?>>Year <?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Step 3: Parent/Guardian Information -->
        <div class="wizard-panel" data-panel="3">
            <h2 class="section-subtitle">Step 3: Parent / Guardian Information</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label for="guardian_name">Parent / Guardian Full Name</label>
                    <input type="text" id="guardian_name" name="guardian_name" value="<?= e($formData['guardian_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="guardian_relationship">Relationship</label>
                    <input type="text" id="guardian_relationship" name="guardian_relationship" placeholder="e.g. Mother, Father, Guardian" value="<?= e($formData['guardian_relationship']) ?>">
                </div>
                <div class="form-group">
                    <label for="guardian_contact">Guardian Contact Number</label>
                    <input type="text" id="guardian_contact" name="guardian_contact" value="<?= e($formData['guardian_contact']) ?>">
                </div>
                <div class="form-group">
                    <label for="guardian_address">Guardian Address</label>
                    <input type="text" id="guardian_address" name="guardian_address" value="<?= e($formData['guardian_address']) ?>">
                </div>
            </div>
        </div>

        <!-- Step 4: Previous School Information -->
        <div class="wizard-panel" data-panel="4">
            <h2 class="section-subtitle">Step 4: Previous School Information</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label for="prev_school_name">Previous School Name</label>
                    <input type="text" id="prev_school_name" name="prev_school_name" value="<?= e($formData['prev_school_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="prev_grade_level">Last Grade / Year Level Completed</label>
                    <input type="text" id="prev_grade_level" name="prev_grade_level" placeholder="e.g. Grade 12 / 2nd Year" value="<?= e($formData['prev_grade_level']) ?>">
                </div>
                <div class="form-group">
                    <label for="prev_track_program">Track / Strand / Previous Program</label>
                    <input type="text" id="prev_track_program" name="prev_track_program" placeholder="e.g. STEM, TVL, BSIT" value="<?= e($formData['prev_track_program']) ?>">
                </div>
                <div class="form-group">
                    <label for="prev_year_graduated">Year Completed / Graduated</label>
                    <input type="text" id="prev_year_graduated" name="prev_year_graduated" placeholder="e.g. 2026" value="<?= e($formData['prev_year_graduated']) ?>">
                </div>
                <div class="form-group">
                    <label for="prev_gwa">General Weighted Average (GWA)</label>
                    <input type="text" id="prev_gwa" name="prev_gwa" placeholder="e.g. 90.5 or 1.75" value="<?= e($formData['prev_gwa']) ?>">
                </div>
            </div>
        </div>

        <!-- Step 5: Requirements Checklist -->
        <div class="wizard-panel" data-panel="5">
            <h2 class="section-subtitle">Step 5: Enrollment Requirements</h2>
            <p class="subtitle" style="margin-bottom:1rem;">
                Required documents are filtered by your classification. After saving or submitting your application, upload your documents on the <a href="requirements.php<?= $existingApp ? '?app_id=' . (int)$existingApp['id'] : '' ?>">My Requirements</a> page.
            </p>
            <div class="req-list" id="requirementsList">
                <?php foreach ($requirements as $req): ?>
                    <div class="req-item" data-applies="<?= e($req['applies_to']) ?>">
                        <div>
                            <strong><?= e($req['name']) ?></strong>
                            <?php if (!empty($req['description'])): ?>
                                <p class="subtitle"><?= e($req['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="badge <?= (int)$req['is_required'] ? 'badge-pending' : 'badge-action' ?>">
                            <?= (int)$req['is_required'] ? 'Required' : 'Optional' ?> (<?= e($req['applies_to']) ?>)
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 6: Review and Submit -->
        <div class="wizard-panel" data-panel="6">
            <h2 class="section-subtitle">Step 6: Review &amp; Submit</h2>
            <div class="review-grid" id="reviewSummary"></div>

            <label class="checkbox-row">
                <input type="checkbox" name="confirm_submit" value="1">
                <span>I confirm that all information provided in this registration application is true and accurate.</span>
            </label>
        </div>

        <div class="wizard-footer">
            <div class="wizard-nav">
                <button type="button" class="btn btn-secondary" id="prevStepBtn" disabled>&larr; Previous</button>
                <button type="button" class="btn btn-secondary" id="nextStepBtn">Next &rarr;</button>
            </div>
            <div class="wizard-submit">
                <button type="submit" name="submit_action" value="draft" class="btn btn-secondary">Save Draft</button>
                <button type="submit" name="submit_action" value="submit" class="btn btn-primary" id="finalSubmitBtn">Submit Application</button>
            </div>
        </div>
    </form>
</section>

<script>
(function () {
    const steps = Array.from(document.querySelectorAll('.wizard-step'));
    const panels = Array.from(document.querySelectorAll('.wizard-panel'));
    const prevBtn = document.getElementById('prevStepBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    const classificationEl = document.getElementById('classification');
    const studentNoGroup = document.getElementById('studentNoGroup');
    const reqItems = Array.from(document.querySelectorAll('.req-item'));
    const reviewSummary = document.getElementById('reviewSummary');
    let currentStep = 1;

    function updateClassificationUI() {
        const cls = classificationEl.value;
        studentNoGroup.style.display = (cls === 'Returning') ? '' : 'none';
        reqItems.forEach((item) => {
            const applies = item.dataset.applies;
            item.style.display = (applies === 'All' || applies === cls) ? '' : 'none';
        });
    }

    function populateReview() {
        const val = (id) => {
            const el = document.getElementById(id);
            if (!el) return '';
            if (el.tagName === 'SELECT') {
                return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '';
            }
            return el.value || '—';
        };
        const rows = [
            ['Full Name', val('full_name')],
            ['Email', val('email')],
            ['Date of Birth', val('date_of_birth')],
            ['Contact No.', val('contact_no')],
            ['Address', val('address')],
            ['Classification', val('classification')],
            ['Student No.', val('student_no')],
            ['Program', val('course_id')],
            ['Campus & Term', `${val('campus')} · ${val('school_year')} (${val('semester')})`],
            ['Year Level', val('year_level')],
            ['Guardian', `${val('guardian_name')} (${val('guardian_relationship')}) · ${val('guardian_contact')}`],
            ['Previous School', `${val('prev_school_name')} (${val('prev_grade_level')})`],
        ];
        reviewSummary.innerHTML = '';
        rows.forEach(([label, text]) => {
            const div = document.createElement('div');
            div.className = 'review-item';
            const dt = document.createElement('span');
            dt.className = 'review-label';
            dt.textContent = label;
            const dd = document.createElement('strong');
            dd.textContent = text;
            div.appendChild(dt);
            div.appendChild(dd);
            reviewSummary.appendChild(div);
        });
    }

    function goToStep(step) {
        currentStep = Math.max(1, Math.min(6, step));
        steps.forEach((btn) => btn.classList.toggle('active', Number(btn.dataset.step) === currentStep));
        panels.forEach((panel) => panel.classList.toggle('active', Number(panel.dataset.panel) === currentStep));
        prevBtn.disabled = currentStep === 1;
        nextBtn.disabled = currentStep === 6;
        if (currentStep === 6) {
            populateReview();
        }
    }

    steps.forEach((btn) => btn.addEventListener('click', () => goToStep(Number(btn.dataset.step))));
    prevBtn.addEventListener('click', () => goToStep(currentStep - 1));
    nextBtn.addEventListener('click', () => goToStep(currentStep + 1));
    classificationEl.addEventListener('change', updateClassificationUI);

    updateClassificationUI();
})();
</script>

<?php render_footer(); ?>
