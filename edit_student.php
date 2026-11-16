<?php
require_once 'db_config.php';

// 1. Fetch the existing data if we are loading the page
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        die("Student not found!");
    }
}

// 2. Handle the form submission to update the database
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $id = $_POST['id'];
        $name = $_POST['full_name'];
        $email = $_POST['email'];
        $course = $_POST['course'];

        $sql = "UPDATE students SET full_name = :name, email = :email, course = :course WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['name' => $name, 'email' => $email, 'course' => $course, 'id' => $id]);
        
        // Redirect back to the table
        header("Location: view_students.php");
        exit;
    } catch(PDOException $e) {
        $error = "Error updating data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Edit Student</h2>
        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
        
        <form method="POST" action="edit_student.php">
            <!-- Hidden input to pass the ID securely -->
            <input type="hidden" name="id" value="<?= htmlspecialchars($student['id']) ?>">
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>
            </div>
            <div class="form-group">
                <label>Course</label>
                <select name="course" required>
                    <option value="BS Computer Science" <?= $student['course'] == 'BS Computer Science' ? 'selected' : '' ?>>BS Computer Science</option>
                    <option value="BS Information Technology" <?= $student['course'] == 'BS Information Technology' ? 'selected' : '' ?>>BS Information Technology</option>
                    <option value="BS Software Engineering" <?= $student['course'] == 'BS Software Engineering' ? 'selected' : '' ?>>BS Software Engineering</option>
                </select>
            </div>
            <button type="submit">Update Student</button>
            <a href="view_students.php" style="display:block; text-align:center; margin-top:10px; color:#555;">Cancel</a>
        </form>
    </div>
</body>
</html>
