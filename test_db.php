<?php
$host = '192.168.100.36';
$dbname = 'student_registration';
$username = 'dbadmin';
$password = 'password123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<h1>Success!</h1>";
    echo "<p>PHP in WSL is connected to MariaDB in the Kali VM.</p>";
} catch(PDOException $e) {
    echo "<h1>Connection Failed</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
