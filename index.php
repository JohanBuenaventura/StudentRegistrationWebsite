<?php
require_once 'db_config.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $name = $_POST['full_name'];
        $email = $_POST['email'];
        $course = $_POST['course'];

        $sql = "INSERT INTO students (full_name, email, course) VALUES (:name, :email, :course)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['name' => $name, 'email' => $email, 'course' => $course]);
        
        $message = "<p class='success'>Student registered successfully!</p>";
    } catch(PDOException $e) {
        $message = "<p class='error'>Error saving data: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Registration</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Student Registration</h2>
        <?= $message ?>
        <form method="POST" action="index.php">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="course">Course</label>
                <select id="course" name="course" required>
                    <option value="">Select a course...</option>
                    <option value="BS Computer Science">BS Computer Science</option>
                    <option value="BS Information Technology">BS Information Technology</option>
                    <option value="BS Software Engineering">BS Software Engineering</option>
                </select>
            </div>
            <button type="submit">Register Student</button>
        </form>
        <br>
        <a href="view_students.php" style="display:block; text-align:center; color:#0056b3; text-decoration:none;">View Registered Students</a>
    </div>
</body>
</html>

