<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['teacher_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');

    if ($name === '') {
        $message = 'Teacher name is required.';
    } else {
        $stmt = $conn->prepare('INSERT INTO teacher (teacher_name, subject) VALUES (?, ?)');
        $stmt->bind_param('ss', $name, $subject);
        $stmt->execute();
        $message = 'Teacher added successfully.';
    }
}

$result = $conn->query('SELECT teacher_id, teacher_name, subject, created_at FROM teacher ORDER BY teacher_id DESC');
$teachers = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Teacher Management</h1>
        <p>Add teachers and manage their subjects.</p>
        <a href="../dashboard.php" class="btn">Back to Dashboard</a>

        <?php if ($message !== ''): ?>
            <p style="color: #0f766e; font-weight: 600; margin-top: 16px;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="dashboard-card" style="margin-top: 24px;">
            <h3>Add Teacher</h3>
            <form method="post">
                <label for="teacher_name">Teacher Name</label>
                <input type="text" id="teacher_name" name="teacher_name" required>

                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" placeholder="e.g. Mathematics">

                <button type="submit">Save Teacher</button>
            </form>
        </div>

        <div class="dashboard-card" style="margin-top: 24px;">
            <h3>Teacher List</h3>
            <?php if (empty($teachers)): ?>
                <p>No teachers yet.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #eff6ff; text-align: left;">
                            <th style="padding: 10px;">Name</th>
                            <th style="padding: 10px;">Subject</th>
                            <th style="padding: 10px;">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($teacher['teacher_name']); ?></td>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($teacher['subject'] ?? '-'); ?></td>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($teacher['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
