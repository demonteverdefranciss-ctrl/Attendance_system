<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['student_name'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $parentName = trim($_POST['parent_name'] ?? '');

    if ($name === '') {
        $message = 'Student name is required.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO student (student_name, section, parent_name) VALUES (?, ?, ?)');
        $stmt->execute([$name, $section, $parentName]);
        $message = 'Student added successfully.';
    }
}

$result = $pdo->query('SELECT student_id, student_name, section, parent_name, created_at FROM student ORDER BY student_id DESC');
$students = $result->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Student Management</h1>
        <p>Register and review students here.</p>
        <a href="../dashboard.php" class="btn">Back to Dashboard</a>

        <?php if ($message !== ''): ?>
            <p style="color: #0f766e; font-weight: 600; margin-top: 16px;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="dashboard-card" style="margin-top: 24px;">
            <h3>Add Student</h3>
            <form method="post">
                <label for="student_name">Student Name</label>
                <input type="text" id="student_name" name="student_name" required>

                <label for="section">Section</label>
                <input type="text" id="section" name="section" placeholder="e.g. Grade 6-A">

                <label for="parent_name">Parent Name</label>
                <input type="text" id="parent_name" name="parent_name">

                <button type="submit">Save Student</button>
            </form>
        </div>

        <div class="dashboard-card" style="margin-top: 24px;">
            <h3>Student List</h3>
            <?php if (empty($students)): ?>
                <p>No students yet.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #eff6ff; text-align: left;">
                            <th style="padding: 10px;">Name</th>
                            <th style="padding: 10px;">Section</th>
                            <th style="padding: 10px;">Parent</th>
                            <th style="padding: 10px;">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($student['student_name']); ?></td>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($student['section'] ?? '-'); ?></td>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($student['parent_name'] ?? '-'); ?></td>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($student['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
