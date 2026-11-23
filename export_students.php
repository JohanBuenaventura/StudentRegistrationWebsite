<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/auth.php';

require_role('admin', 'registrar');

$queryInfo = build_student_filter_query($_GET);

try {
    $stmt = $pdo->prepare($queryInfo['sql']);
    $stmt->execute($queryInfo['params']);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash('error', 'Unable to export student records.');
    header('Location: view_students.php');
    exit;
}

log_activity(
    $pdo,
    'EXPORT_CSV',
    'student',
    null,
    'Exported ' . count($students) . ' student record(s) to CSV'
);

$filename = 'wmsu-students-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Student No',
    'Full Name',
    'Email',
    'Course Code',
    'Course Name',
    'Campus',
    'Year Level',
    'School Year',
    'Semester',
    'Status',
    'Registered At',
]);

foreach ($students as $student) {
    fputcsv($output, [
        $student['student_no'] ?? '',
        $student['full_name'] ?? '',
        $student['email'] ?? '',
        $student['course_code'] ?? '',
        $student['course'] ?? '',
        $student['campus'] ?? 'Main - Baliwasan',
        $student['year_level'] ?? '',
        $student['school_year'] ?? '2026-2027',
        $student['semester'] ?? '1st Semester',
        $student['status'] ?? '',
        $student['registration_date'] ?? '',
    ]);
}

fclose($output);
exit;
