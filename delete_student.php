<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/auth.php';

require_role('admin', 'registrar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method for deletion.');
    header('Location: view_students.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid session token. Deletion aborted.');
    header('Location: view_students.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    set_flash('error', 'Invalid student ID.');
    header('Location: view_students.php');
    exit;
}

try {
    $lookup = $pdo->prepare('SELECT student_no, full_name FROM students WHERE id = :id LIMIT 1');
    $lookup->execute(['id' => $id]);
    $student = $lookup->fetch();

    if (!$student) {
        set_flash('error', 'Student record was not found.');
        header('Location: view_students.php');
        exit;
    }

    $deleteStmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
    $deleteStmt->execute(['id' => $id]);

    log_activity(
        $pdo,
        'DELETE_STUDENT',
        'student',
        $id,
        "Deleted {$student['full_name']} ({$student['student_no']})"
    );

    set_flash('success', "Deleted student {$student['full_name']}.");
} catch (PDOException $e) {
    set_flash('error', 'Unable to delete student record.');
}

header('Location: view_students.php');
exit;
