<?php
// CHANGE THIS IP TO YOUR PHONE HOTSPOT IP DURING THE DEMO
$host = '192.168.62.204'; 
$dbname = 'student_registration';
$username = 'dbadmin';
$password = 'password123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("<p class='error'>Database Connection Failed: " . $e->getMessage() . "</p>");
}
?>
