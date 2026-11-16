<?php
require_once 'db_config.php';

if (isset($_GET['id'])) {
    try {
        $sql = "DELETE FROM students WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $_GET['id']]);
    } catch(PDOException $e) {
        die("Error deleting record: " . $e->getMessage());
    }
}

// Redirect back to the view page
header("Location: view_students.php");
exit;
?>
