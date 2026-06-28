<?php
require_once '../config/database.php';

$username = 'teacher';
$password = 'teacher123';
$role = 'teacher';

try {
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert the user
    $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
    $stmt->execute([$username, $hashed_password, $role]);
    
    echo "✓ Teacher account created successfully!<br><br>";
    echo "Username: <strong>$username</strong><br>";
    echo "Password: <strong>$password</strong><br>";
    echo "Role: <strong>$role</strong><br><br>";
    
    // Show all users
    $result = $pdo->query('SELECT id, username, role FROM users ORDER BY id DESC');
    $users = $result->fetchAll();
    
    echo "All user accounts:<br>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
    foreach ($users as $u) {
        echo "<tr>";
        echo "<td>" . $u['id'] . "</td>";
        echo "<td>" . $u['username'] . "</td>";
        echo "<td>" . $u['role'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
