<?php
// CHANGE THIS IP TO YOUR KALI VM / PHONE HOTSPOT IP DURING THE DEMO
// Optionally override via the DB_HOST environment variable
$envHost = getenv('DB_HOST');
$host = ($envHost !== false && trim($envHost) !== '') ? trim($envHost) : '192.168.1.10';
$dbname = 'student_registration';
$username = 'dbadmin';
$password = 'password123';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(503);
    $safeHost = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Database Unavailable</title>'
        . '<link rel="stylesheet" href="style.css"></head><body>'
        . '<main class="page-wrapper"><div class="card card-narrow">'
        . '<h2>Database Connection Unavailable</h2>'
        . '<p class="alert alert-error">Unable to reach the MariaDB server on the Kali VM '
        . "(<code>{$safeHost}</code>). Ensure the VM is running, MariaDB is active, and "
        . '<code>db_config.php</code> has the current bridged IP.</p>'
        . '</div></main></body></html>';
    exit;
}
