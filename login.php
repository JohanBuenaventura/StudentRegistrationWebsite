<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$maxAttempts = 5;
$lockoutSeconds = 60;
$attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'locked_until' => 0];
$now = time();

$error = '';
$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? null;

    if ($attempts['locked_until'] > $now) {
        $remaining = $attempts['locked_until'] - $now;
        $error = "Too many failed login attempts. Please wait {$remaining} second(s) before trying again.";
    } elseif (!verify_csrf($token)) {
        $error = 'Invalid session token. Please try again.';
    } elseif ($usernameInput === '' || $passwordInput === '') {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, username, full_name, password_hash, role
                 FROM users
                 WHERE username = :username
                 LIMIT 1'
            );
            $stmt->execute(['username' => $usernameInput]);
            $user = $stmt->fetch();

            if ($user && password_verify($passwordInput, $user['password_hash'])) {
                unset($_SESSION['login_attempts']);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'        => (int)$user['id'],
                    'username'  => $user['username'],
                    'full_name' => $user['full_name'],
                    'role'      => $user['role'],
                ];

                log_activity($pdo, 'LOGIN_SUCCESS', 'auth', (int)$user['id'], 'Signed in to WMSU Registrar Portal');
                set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
                header('Location: index.php');
                exit;
            }

            $attempts['count'] = (int)$attempts['count'] + 1;
            if ($attempts['count'] >= $maxAttempts) {
                $attempts['locked_until'] = $now + $lockoutSeconds;
                $attempts['count'] = 0;
                $error = "Too many failed login attempts. Please wait {$lockoutSeconds} seconds before trying again.";
            } else {
                $error = 'Invalid username or password.';
            }
            $_SESSION['login_attempts'] = $attempts;

            log_activity(
                $pdo,
                'LOGIN_FAILED',
                'auth',
                $user ? (int)$user['id'] : null,
                "Failed sign-in attempt for username '{$usernameInput}'"
            );
        } catch (PDOException $e) {
            $error = 'Unable to verify credentials right now.';
        }
    }
}

render_header('Sign In', 'login');
?>

<section class="card card-narrow">
    <div class="card-header">
        <div>
            <h1>Registrar Sign In</h1>
            <p class="subtitle">Authenticate to register, edit, or remove student records.</p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="form-group">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                value="<?= e($usernameInput) ?>"
                autocomplete="username"
                required
                autofocus
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>

    <p class="helper-note">
        Default seeded account: <code>admin</code> / <code>admin123</code> (change after setup).
    </p>
</section>

<?php render_footer(); ?>
