<?php
require_once 'db_config.php';

try {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY registration_date DESC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("<p class='error'>Error fetching data: " . $e->getMessage() . "</p>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Students</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .table-container { max-width: 900px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #0056b3; color: white; }
        tr:hover { background-color: #f9f9f9; }
        .nav-link { display: inline-block; margin-bottom: 15px; text-decoration: none; color: #0056b3; font-weight: bold; }
        .action-link { text-decoration: none; padding: 5px 10px; border-radius: 3px; color: white; font-size: 14px; }
        .edit-btn { background-color: #28a745; margin-right: 5px; }
        .delete-btn { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container table-container">
        <a href="index.php" class="nav-link">&larr; Back to Registration</a>
        <h2>Registered Students</h2>
        
        <?php if (count($students) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['id']) ?></td>
                            <td><?= htmlspecialchars($student['full_name']) ?></td>
                            <td><?= htmlspecialchars($student['email']) ?></td>
                            <td><?= htmlspecialchars($student['course']) ?></td>
                            <td>
                                <!-- Pass the specific student ID in the URL -->
                                <a href="edit_student.php?id=<?= $student['id'] ?>" class="action-link edit-btn">Edit</a>
                                <a href="delete_student.php?id=<?= $student['id'] ?>" class="action-link delete-btn" onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No students registered yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
