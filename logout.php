<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $user = current_user();
    log_activity($pdo, 'LOGOUT', 'auth', (int)$user['id'], 'Signed out of Registrar Portal');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

session_start();
set_flash('success', 'You have been signed out.');
header('Location: login.php');
exit;
