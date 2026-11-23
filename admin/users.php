<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('admin');

$currentAdminId = (int)current_user()['id'];
$errors = [];
$formData = [
    'username'  => '',
    'full_name' => '',
    'email'     => '',
    'role'      => 'registrar',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid session token.');
        header('Location: users.php');
        exit;
    }

    $action = $_POST['action'] ?? 'create';

    if ($action === 'toggle_active') {
        $targetId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$targetId || $targetId <= 0) {
            set_flash('error', 'Invalid user ID.');
        } elseif ($targetId === $currentAdminId) {
            set_flash('error', 'You cannot deactivate your own active admin account.');
        } else {
            $uStmt = $pdo->prepare('SELECT id, username, is_active FROM users WHERE id = :id LIMIT 1');
            $uStmt->execute(['id' => $targetId]);
            $targetUser = $uStmt->fetch();
            if ($targetUser) {
                $newState = (int)$targetUser['is_active'] ? 0 : 1;
                $upd = $pdo->prepare('UPDATE users SET is_active = :state WHERE id = :id');
                $upd->execute(['state' => $newState, 'id' => $targetId]);
                log_activity(
                    $pdo,
                    'TOGGLE_USER_STATUS',
                    'user',
                    $targetId,
                    ($newState ? 'Activated' : 'Deactivated') . " user '{$targetUser['username']}'"
                );
                set_flash('success', "Updated status for {$targetUser['username']}.");
            }
        }
        header('Location: users.php');
        exit;
    }

    $formData['username']  = trim($_POST['username'] ?? '');
    $formData['full_name'] = trim($_POST['full_name'] ?? '');
    $formData['email']     = trim($_POST['email'] ?? '');
    $formData['role']      = trim($_POST['role'] ?? 'registrar');
    $password              = $_POST['password'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $formData['username'])) {
        $errors[] = 'Username must be 3-50 characters.';
    }
    if ($formData['full_name'] === '' || strlen($formData['full_name']) > 100) {
        $errors[] = 'Full name is required (max 100 characters).';
    }
    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (!in_array($formData['role'], ['admin', 'registrar'], true)) {
        $errors[] = 'Staff role must be admin or registrar.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (empty($errors)) {
        try {
            $dup = $pdo->prepare('SELECT id FROM users WHERE username = :username OR (:email != "" AND email = :email2) LIMIT 1');
            $dup->execute([
                'username' => $formData['username'],
                'email'    => $formData['email'],
                'email2'   => $formData['email'],
            ]);
            if ($dup->fetch()) {
                $errors[] = 'Username or email already exists.';
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO users (username, full_name, email, password_hash, role, is_active)
                     VALUES (:username, :full_name, :email, :password_hash, :role, 1)'
                );
                $ins->execute([
                    'username'      => $formData['username'],
                    'full_name'     => $formData['full_name'],
                    'email'         => $formData['email'] !== '' ? $formData['email'] : null,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role'          => $formData['role'],
                ]);
                $newId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'CREATE_USER', 'user', $newId, "Created {$formData['role']} account '{$formData['username']}'");
                set_flash('success', "Created {$formData['role']} account '{$formData['username']}'.");
                header('Location: users.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Unable to create user account.';
        }
    }
}

$users = $pdo->query(
    'SELECT id, username, full_name, email, role, is_active, student_id, created_at
     FROM users
     ORDER BY FIELD(role, "admin", "registrar", "student"), created_at DESC'
)->fetchAll();

render_header('User Management', 'admin_users');
?>

<div class="split-layout">
    <section class="card">
        <div class="card-header">
            <div>
                <h1>Create Staff Account</h1>
                <p class="subtitle">Add a Registrar or Admin user.</p>
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

        <form method="POST" action="users.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?= e($formData['username']) ?>" required>
            </div>
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email (Optional)</label>
                <input type="email" id="email" name="email" value="<?= e($formData['email']) ?>">
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="registrar" <?= $formData['role'] === 'registrar' ? 'selected' : '' ?>>Registrar</option>
                    <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Password (min. 8 characters)</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>
    </section>

    <section class="card">
        <div class="card-header">
            <div>
                <h2>System Accounts</h2>
                <p class="subtitle"><?= count($users) ?> total user<?= count($users) === 1 ? '' : 's' ?>.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><code><?= e($u['username']) ?></code></td>
                            <td>
                                <strong><?= e($u['full_name']) ?></strong>
                                <?php if (!empty($u['email'])): ?>
                                    <br><small class="text-muted-link"><?= e($u['email']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-action"><?= e($u['role']) ?></span></td>
                            <td>
                                <span class="badge <?= (int)$u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= (int)$u['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int)$u['id'] !== $currentAdminId): ?>
                                    <form method="POST" action="users.php" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            <?= (int)$u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
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
</div>

<?php render_footer(); ?>
