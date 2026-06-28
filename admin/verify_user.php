<?php
require_once '../config/database.php';

// First, check if teacher account already exists
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute(['teacher']);
$existing = $stmt->fetch();

if ($existing) {
    echo "✓ Teacher account already exists!<br>";
    echo "ID: " . $existing['id'] . "<br>";
    echo "Username: " . $existing['username'] . "<br>";
    echo "Role: " . $existing['role'] . "<br><br>";
} else {
    echo "Creating teacher account...<br>";
    $username = 'teacher';
    $password = 'teacher123';
    $role = 'teacher';
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
    $result = $stmt->execute([$username, $hashed_password, $role]);
    
    if ($result) {
        echo "✓ Teacher account created successfully!<br>";
        echo "Username: <strong>$username</strong><br>";
        echo "Password: <strong>$password</strong><br>";
        echo "Role: <strong>$role</strong><br><br>";
    } else {
        echo "✗ Failed to create account<br>";
    }
}

// Show all users
echo "<hr>";
echo "All user accounts in database:<br>";
$result = $pdo->query('SELECT id, username, role FROM users');
$users = $result->fetchAll();

if (empty($users)) {
    echo "No users found!<br>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
    foreach ($users as $u) {
        echo "<tr>";
        echo "<td>" . $u['id'] . "</td>";
        echo "<td>" . $u['username'] . "</td>";
        echo "<td>" . $u['role'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
}

// Test password verification
echo "<hr>";
echo "Testing password verification:<br>";
if ($existing) {
    $test_password = 'teacher123';
    $is_valid = password_verify($test_password, $existing['password']);
    echo "Password 'teacher123' is " . ($is_valid ? "✓ VALID" : "✗ INVALID") . "<br>";
}
?>
