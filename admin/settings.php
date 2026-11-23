<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('admin');

$semesters = allowed_semesters();
$appliesOptions = ['All', 'New', 'Returning', 'Transferee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid session token.');
        header('Location: settings.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_term') {
            $label = trim($_POST['label'] ?? '');
            $semester = trim($_POST['semester'] ?? '1st Semester');
            if (!preg_match('/^\d{4}-\d{4}$/', $label) || !in_array($semester, $semesters, true)) {
                set_flash('error', 'Invalid school year (YYYY-YYYY) or semester.');
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO school_years (label, semester, is_active, registration_open)
                     VALUES (:label, :semester, 0, 1)'
                );
                $ins->execute(['label' => $label, 'semester' => $semester]);
                log_activity($pdo, 'UPDATE_SCHOOL_YEAR', 'school_year', (int)$pdo->lastInsertId(), "Added term {$label} ({$semester})");
                set_flash('success', "Added school year {$label}.");
            }
        } elseif ($action === 'activate_term') {
            $termId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($termId && $termId > 0) {
                $pdo->beginTransaction();
                $pdo->exec('UPDATE school_years SET is_active = 0');
                $upd = $pdo->prepare('UPDATE school_years SET is_active = 1 WHERE id = :id');
                $upd->execute(['id' => $termId]);
                $pdo->commit();
                log_activity($pdo, 'UPDATE_SCHOOL_YEAR', 'school_year', $termId, "Activated school year #{$termId}");
                set_flash('success', 'Active school year updated.');
            }
        } elseif ($action === 'add_requirement') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $appliesTo = trim($_POST['applies_to'] ?? 'All');
            $isRequired = !empty($_POST['is_required']) ? 1 : 0;

            if (!preg_match('/^[A-Z0-9_]{2,30}$/', $code) || $name === '' || !in_array($appliesTo, $appliesOptions, true)) {
                set_flash('error', 'Invalid requirement code (A-Z, 0-9, _), name, or applicability.');
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO registration_requirements (code, name, description, applies_to, is_required, is_active)
                     VALUES (:code, :name, :description, :applies_to, :is_required, 1)'
                );
                $ins->execute([
                    'code'        => $code,
                    'name'        => $name,
                    'description' => $desc !== '' ? $desc : null,
                    'applies_to'  => $appliesTo,
                    'is_required' => $isRequired,
                ]);
                log_activity($pdo, 'UPDATE_REQUIREMENT', 'requirement', (int)$pdo->lastInsertId(), "Added requirement {$code}");
                set_flash('success', "Added requirement {$name}.");
            }
        } elseif ($action === 'toggle_requirement') {
            $reqId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($reqId && $reqId > 0) {
                $upd = $pdo->prepare('UPDATE registration_requirements SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
                $upd->execute(['id' => $reqId]);
                log_activity($pdo, 'UPDATE_REQUIREMENT', 'requirement', $reqId, "Toggled active status for requirement #{$reqId}");
                set_flash('success', 'Requirement status updated.');
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('error', 'Unable to save setting (possible duplicate code or label).');
    }

    header('Location: settings.php');
    exit;
}

$schoolYears = $pdo->query('SELECT * FROM school_years ORDER BY label DESC')->fetchAll();
$requirements = $pdo->query('SELECT * FROM registration_requirements ORDER BY is_active DESC, name ASC')->fetchAll();

render_header('Registration Settings', 'admin_settings');
?>

<div class="split-layout">
    <section class="card">
        <div class="card-header">
            <div>
                <h1>Academic Terms</h1>
                <p class="subtitle">Manage active school years for registration.</p>
            </div>
        </div>

        <form method="POST" action="settings.php" style="margin-bottom:1.25rem;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_term">
            <div class="form-group">
                <label for="label">School Year (YYYY-YYYY)</label>
                <input type="text" id="label" name="label" placeholder="e.g. 2027-2028" required>
            </div>
            <div class="form-group">
                <label for="semester">Default Semester</label>
                <select id="semester" name="semester">
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= e($sem) ?>"><?= e($sem) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Add School Year</button>
        </form>

        <div class="table-responsive">
            <table>
                <thead><tr><th>Term</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($schoolYears as $sy): ?>
                        <tr>
                            <td><strong><?= e($sy['label']) ?></strong><br><small class="text-muted-link"><?= e($sy['semester']) ?></small></td>
                            <td>
                                <span class="badge <?= (int)$sy['is_active'] ? 'badge-active' : 'badge-action' ?>">
                                    <?= (int)$sy['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!(int)$sy['is_active']): ?>
                                    <form method="POST" action="settings.php" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="activate_term">
                                        <input type="hidden" name="id" value="<?= (int)$sy['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">Set Active</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted-link">Current</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h2>Registration Requirements</h2>
                <p class="subtitle">Configure required documents by applicant classification.</p>
            </div>
        </div>

        <form method="POST" action="settings.php" class="form-grid" style="margin-bottom:1.25rem;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_requirement">
            <div class="form-group">
                <label for="code">Code</label>
                <input type="text" id="code" name="code" placeholder="e.g. MED_CERT" required>
            </div>
            <div class="form-group">
                <label for="name">Document Name</label>
                <input type="text" id="name" name="name" placeholder="e.g. Medical Certificate" required>
            </div>
            <div class="form-group">
                <label for="applies_to">Applies To</label>
                <select id="applies_to" name="applies_to">
                    <?php foreach ($appliesOptions as $opt): ?>
                        <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" id="description" name="description">
            </div>
            <div class="form-group form-full">
                <label class="checkbox-row">
                    <input type="checkbox" name="is_required" value="1" checked>
                    <span>Required for submission</span>
                </label>
            </div>
            <div class="form-actions form-full">
                <button type="submit" class="btn btn-primary btn-sm">Add Requirement</button>
            </div>
        </form>

        <div class="table-responsive">
            <table>
                <thead><tr><th>Code</th><th>Requirement</th><th>Applies To</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($requirements as $req): ?>
                        <tr>
                            <td><code><?= e($req['code']) ?></code></td>
                            <td><strong><?= e($req['name']) ?></strong></td>
                            <td><?= e($req['applies_to']) ?></td>
                            <td>
                                <span class="badge <?= (int)$req['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= (int)$req['is_active'] ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="settings.php" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle_requirement">
                                    <input type="hidden" name="id" value="<?= (int)$req['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <?= (int)$req['is_active'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php render_footer(); ?>
