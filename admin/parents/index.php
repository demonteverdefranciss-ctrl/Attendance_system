<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['parent_name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');

    if ($name === '') {
        $message = 'Parent name is required.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO parent (parent_name, contact) VALUES (?, ?)');
        $stmt->execute([$name, $contact]);
        $message = 'Parent added successfully.';
    }
}

$result = $pdo->query('SELECT parent_id, parent_name, contact, created_at FROM parent ORDER BY parent_id DESC');
$parents = $result->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Parents</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1>Parent Management</h1>
        <p>Add parents and keep their contact details.</p>
        <a href="../dashboard.php" class="btn">Back to Dashboard</a>

        <?php if ($message !== ''): ?>
            <p style="color: #0f766e; font-weight: 600; margin-top: 16px;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="dashboard-card" style="margin-top: 24px;">
            <h3>Add Parent</h3>
            <form method="post">
                <label for="parent_name">Parent Name</label>
                <input type="text" id="parent_name" name="parent_name" required>

                <label for="contact">Contact</label>
                <input type="text" id="contact" name="contact" placeholder="Phone or email">

                <button type="submit">Save Parent</button>
            </form>
        </div>

        <div class="dashboard-card" style="margin-top: 24px;">
            <h3>Parent List</h3>
            <?php if (empty($parents)): ?>
                <p>No parents yet.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #eff6ff; text-align: left;">
                            <th style="padding: 10px;">Name</th>
                            <th style="padding: 10px;">Contact</th>
                            <th style="padding: 10px;">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parents as $parent): ?>
                            <tr>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($parent['parent_name']); ?></td>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($parent['contact'] ?? '-'); ?></td>
                                <td style="padding: 10px; border-top: 1px solid #e2e8f0;"><?php echo htmlspecialchars($parent['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
