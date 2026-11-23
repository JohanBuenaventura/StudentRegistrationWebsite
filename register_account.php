<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    header('Location: ' . role_home_url());
    exit;
}

$formData = [
    'full_name' => '',
    'email'     => '',
    'username'  => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please refresh and try again.';
    }

    $formData['full_name'] = trim($_POST['full_name'] ?? '');
    $formData['email']     = trim($_POST['email'] ?? '');
    $formData['username']  = trim($_POST['username'] ?? '');
    $password              = $_POST['password'] ?? '';
    $confirmPassword       = $_POST['confirm_password'] ?? '';

    if ($formData['full_name'] === '' || strlen($formData['full_name']) > 100) {
        $errors[] = 'Full name is required (max 100 characters).';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL) || strlen($formData['email']) > 100) {
        $errors[] = 'Please provide a valid email address (max 100 characters).';
    }
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $formData['username'])) {
        $errors[] = 'Username must be 3-50 characters (letters, numbers, dot, underscore, or hyphen).';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if (empty($errors)) {
        try {
            $dupStmt = $pdo->prepare(
                'SELECT username, email FROM users WHERE username = :username OR email = :email LIMIT 1'
            );
            $dupStmt->execute([
                'username' => $formData['username'],
                'email'    => $formData['email'],
            ]);
            $existing = $dupStmt->fetch();

            if ($existing) {
                if (strcasecmp((string)$existing['username'], $formData['username']) === 0) {
                    $errors[] = 'That username is already taken.';
                }
                if ($existing['email'] !== null && strcasecmp((string)$existing['email'], $formData['email']) === 0) {
                    $errors[] = 'An account with that email address already exists.';
                }
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare(
                    "INSERT INTO users (username, full_name, email, password_hash, role, is_active, student_id)
                     VALUES (:username, :full_name, :email, :password_hash, 'student', 1, NULL)"
                );
                $insertStmt->execute([
                    'username'      => $formData['username'],
                    'full_name'     => $formData['full_name'],
                    'email'         => $formData['email'],
                    'password_hash' => $hash,
                ]);

                $newUserId = (int)$pdo->lastInsertId();
                log_activity(
                    $pdo,
                    'REGISTER_STUDENT_ACCOUNT',
                    'user',
                    $newUserId,
                    "Created student portal account '{$formData['username']}'"
                );

                set_flash('success', 'Student account created! You can now sign in and submit your registration application.');
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = 'Unable to create account right now. Ensure the database migration has been applied.';
        }
    }
}

render_header('Student Sign Up', 'signup');
?>

<section class="card card-narrow">
    <div class="card-header">
        <div>
            <h1>Create Student Account</h1>
            <p class="subtitle">Sign up to complete your WMSU online registration application.</p>
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

    <form method="POST" action="register_account.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                placeholder="e.g. Juan Dela Cruz"
                value="<?= e($formData['full_name']) ?>"
                required
                autofocus
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
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                placeholder="e.g. juan.delacruz"
                value="<?= e($formData['username']) ?>"
                autocomplete="username"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password (min. 8 characters)</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
                required
            >
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                autocomplete="new-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
    </form>

    <p class="helper-note">
        Already have an account? <a href="login.php">Sign in here</a>
    </p>
</section>

<?php render_footer(); ?>
