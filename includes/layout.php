<?php
require_once __DIR__ . '/auth.php';

/**
 * Render the shared page header, role-aware top navigation bar, and flash banner.
 */
function render_header(string $pageTitle, string $activeNav = ''): void
{
    global $pdo;
    $user = current_user();
    $flash = get_flash();
    $base = base_path_prefix();
    $role = $user['role'] ?? '';
    $homeHref = $base . ($role === 'student' ? 'student/dashboard.php' : 'registrar/dashboard.php');
    $unreadNotifs = 0;

    if ($user && isset($pdo) && $pdo instanceof PDO && $role === 'student') {
        try {
            $nStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
            $nStmt->execute(['uid' => (int)$user['id']]);
            $unreadNotifs = (int)$nStmt->fetchColumn();
        } catch (PDOException $e) {
            $unreadNotifs = 0;
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — WMSU Office of the University Registrar</title>
    <link rel="stylesheet" href="<?= e($base) ?>style.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="<?= e($homeHref) ?>" class="brand">
                <span class="brand-mark">WMSU</span>
                <span class="brand-text">
                    <span>WMSU Registrar Portal</span>
                    <small class="brand-sub">Zamboanga City</small>
                </span>
            </a>

            <nav class="nav-links" aria-label="Primary navigation">
                <?php if ($role === 'student'): ?>
                    <a href="<?= e($base) ?>student/dashboard.php" class="nav-link <?= $activeNav === 'student_dashboard' ? 'active' : '' ?>">Dashboard</a>
                    <a href="<?= e($base) ?>student/apply.php" class="nav-link <?= $activeNav === 'student_apply' ? 'active' : '' ?>">My Registration</a>
                    <a href="<?= e($base) ?>student/requirements.php" class="nav-link <?= $activeNav === 'student_requirements' ? 'active' : '' ?>">Requirements</a>
                    <a href="<?= e($base) ?>notifications.php" class="nav-link <?= $activeNav === 'notifications' ? 'active' : '' ?>">
                        Notifications<?php if ($unreadNotifs > 0): ?> <span class="notif-count"><?= $unreadNotifs ?></span><?php endif; ?>
                    </a>
                    <a href="<?= e($base) ?>student/profile.php" class="nav-link <?= $activeNav === 'student_profile' ? 'active' : '' ?>">My Profile</a>
                <?php elseif (in_array($role, ['admin', 'registrar'], true)): ?>
                    <a href="<?= e($base) ?>registrar/dashboard.php" class="nav-link <?= $activeNav === 'registrar_dashboard' ? 'active' : '' ?>">Dashboard</a>
                    <a href="<?= e($base) ?>registrar/applications.php" class="nav-link <?= $activeNav === 'registrar_applications' ? 'active' : '' ?>">Applications</a>
                    <a href="<?= e($base) ?>index.php" class="nav-link <?= $activeNav === 'register' ? 'active' : '' ?>">Register</a>
                    <a href="<?= e($base) ?>view_students.php" class="nav-link <?= $activeNav === 'students' ? 'active' : '' ?>">Students</a>
                    <a href="<?= e($base) ?>courses.php" class="nav-link <?= $activeNav === 'courses' ? 'active' : '' ?>">Courses</a>
                    <a href="<?= e($base) ?>reports.php" class="nav-link <?= $activeNav === 'reports' ? 'active' : '' ?>">Reports</a>
                    <a href="<?= e($base) ?>logs.php" class="nav-link <?= $activeNav === 'logs' ? 'active' : '' ?>">Audit Logs</a>
                    <?php if ($role === 'admin'): ?>
                        <a href="<?= e($base) ?>admin/users.php" class="nav-link <?= $activeNav === 'admin_users' ? 'active' : '' ?>">Users</a>
                        <a href="<?= e($base) ?>admin/settings.php" class="nav-link <?= $activeNav === 'admin_settings' ? 'active' : '' ?>">Settings</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= e($base) ?>login.php" class="nav-link <?= $activeNav === 'login' ? 'active' : '' ?>">Sign In</a>
                    <a href="<?= e($base) ?>register_account.php" class="nav-link <?= $activeNav === 'signup' ? 'active' : '' ?>">Student Sign Up</a>
                <?php endif; ?>
            </nav>

            <div class="nav-user">
                <?php if ($user): ?>
                    <span class="user-pill">
                        <?= e($user['full_name']) ?>
                        <small>(<?= e($user['role']) ?>)</small>
                    </span>
                    <a href="<?= e($base) ?>logout.php" class="btn btn-secondary btn-sm">Sign Out</a>
                <?php else: ?>
                    <a href="<?= e($base) ?>login.php" class="btn btn-primary btn-sm">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="page-wrapper">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>
    <?php
}

/**
 * Render the shared page footer.
 */
function render_footer(): void
{
    ?>
    </main>
    <footer class="site-footer">
        <p>Western Mindanao State University &middot; Office of the University Registrar &middot; Zamboanga City</p>
    </footer>
</body>
</html>
    <?php
}
