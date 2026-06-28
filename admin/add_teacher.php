<?php
require_once '../config/database.php';

// Add a sample teacher
$teacher_name = 'Mr. John Smith';
$subject = 'Mathematics';

try {
    $stmt = $pdo->prepare('INSERT INTO teacher (teacher_name, subject) VALUES (?, ?)');
    $stmt->execute([$teacher_name, $subject]);
    echo "✓ Teacher added successfully!<br>";
    echo "Name: $teacher_name<br>";
    echo "Subject: $subject<br><br>";
    
    // Show all teachers
    $result = $pdo->query('SELECT * FROM teacher ORDER BY teacher_id DESC');
    $teachers = $result->fetchAll();
    
    echo "All teachers:<br>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Subject</th><th>Created</th></tr>";
    foreach ($teachers as $t) {
        echo "<tr>";
        echo "<td>" . $t['teacher_id'] . "</td>";
        echo "<td>" . $t['teacher_name'] . "</td>";
        echo "<td>" . $t['subject'] . "</td>";
        echo "<td>" . $t['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
