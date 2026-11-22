<?php
require_once __DIR__ . '/auth.php';

/**
 * Render the shared page header, top navigation bar, and flash banner.
 */
function render_header(string $pageTitle, string $activeNav = ''): void
{
    $user = current_user();
    $flash = get_flash();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — WMSU Office of the University Registrar</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="view_students.php" class="brand">
                <span class="brand-mark">WMSU</span>
                <span class="brand-text">
                    <span>WMSU Registrar Portal</span>
                    <small class="brand-sub">Zamboanga City</small>
                </span>
            </a>

            <nav class="nav-links" aria-label="Primary navigation">
                <a href="index.php" class="nav-link <?= $activeNav === 'register' ? 'active' : '' ?>">Register</a>
                <a href="view_students.php" class="nav-link <?= $activeNav === 'students' ? 'active' : '' ?>">Students</a>
                <a href="courses.php" class="nav-link <?= $activeNav === 'courses' ? 'active' : '' ?>">Courses</a>
                <?php if ($user): ?>
                    <a href="logs.php" class="nav-link <?= $activeNav === 'logs' ? 'active' : '' ?>">Audit Logs</a>
                <?php endif; ?>
            </nav>

            <div class="nav-user">
                <?php if ($user): ?>
                    <span class="user-pill">
                        <?= e($user['full_name']) ?>
                        <small>(<?= e($user['role']) ?>)</small>
                    </span>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Sign Out</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-sm">Sign In</a>
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
