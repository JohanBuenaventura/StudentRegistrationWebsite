<?php
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/layout.php';

require_role('student');

$sessionUser = current_user();
$userId = (int)$sessionUser['id'];

$stmt = $pdo->prepare('SELECT id, username, full_name, email, role, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$userRecord = $stmt->fetch();

if (!$userRecord) {
    set_flash('error', 'Account not found.');
    header('Location: ../logout.php');
    exit;
}

$formData = [
    'full_name' => $userRecord['full_name'] ?? '',
    'email'     => $userRecord['email'] ?? '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please refresh and try again.';
    }

    $formData['full_name'] = trim($_POST['full_name'] ?? '');
    $formData['email']     = trim($_POST['email'] ?? '');
    $currentPassword       = $_POST['current_password'] ?? '';
    $newPassword           = $_POST['new_password'] ?? '';
    $confirmPassword       = $_POST['confirm_password'] ?? '';

    if ($formData['full_name'] === '' || strlen($formData['full_name']) > 100) {
        $errors[] = 'Full name is required (max 100 characters).';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || strlen($formData['email']) > 100) {
        $errors[] = 'Please provide a valid email address (max 100 characters).';
    }

    $changingPassword = ($newPassword !== '' || $confirmPassword !== '');
    if ($changingPassword) {
        if ($currentPassword === '') {
            $errors[] = 'Enter your current password to set a new password.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }
    }

    if (empty($errors)) {
        try {
            $dupStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
            $dupStmt->execute([
                'email' => $formData['email'],
                'id'    => $userId,
            ]);
            if ($dupStmt->fetch()) {
                $errors[] = 'That email address is already used by another account.';
            } else {
                if ($changingPassword) {
                    $pwStmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
                    $pwStmt->execute(['id' => $userId]);
                    $hashRow = $pwStmt->fetch();

                    if (!$hashRow || !password_verify($currentPassword, $hashRow['password_hash'])) {
                        $errors[] = 'Current password is incorrect.';
                    } else {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare(
                            'UPDATE users SET full_name = :full_name, email = :email, password_hash = :password_hash WHERE id = :id'
                        );
                        $upd->execute([
                            'full_name'     => $formData['full_name'],
                            'email'         => $formData['email'],
                            'password_hash' => $newHash,
                            'id'            => $userId,
                        ]);
                    }
                } else {
                    $upd = $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email WHERE id = :id');
                    $upd->execute([
                        'full_name' => $formData['full_name'],
                        'email'     => $formData['email'],
                        'id'        => $userId,
                    ]);
                }

                if (empty($errors)) {
                    $_SESSION['user']['full_name'] = $formData['full_name'];
                    $_SESSION['user']['email'] = $formData['email'];

                    log_activity(
                        $pdo,
                        'UPDATE_STUDENT_PROFILE',
                        'user',
                        $userId,
                        $changingPassword ? 'Updated profile and password' : 'Updated profile details'
                    );

                    set_flash('success', 'Your profile has been updated.');
                    header('Location: profile.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Unable to update profile right now.';
        }
    }
}

render_header('My Profile', 'student_profile');
?>

<section class="card card-medium">
    <div class="card-header">
        <div>
            <h1>My Student Profile</h1>
            <p class="subtitle">Username: <code><?= e($userRecord['username']) ?></code> &middot; Account created <?= e(date('M j, Y', strtotime($userRecord['created_at']))) ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">&larr; Dashboard</a>
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

    <form method="POST" action="profile.php" class="form-grid" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
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
                value="<?= e($formData['email']) ?>"
                required
            >
        </div>

        <div class="form-group form-full">
            <hr class="divider">
            <h2 class="section-subtitle">Change Password (Optional)</h2>
        </div>

        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password">
        </div>

        <div class="form-group">
            <label for="new_password">New Password (min. 8 characters)</label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password">
        </div>

        <div class="form-group form-full">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
        </div>

        <div class="form-actions form-full">
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
    </form>
</section>

<?php render_footer(); ?>
