<?php
// PDO database configuration for the attendance system.
$host = 'localhost';
$dbname = 'attendance_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Convert any legacy plaintext passwords to password_hash format.
    $legacyUsers = $pdo->query('SELECT id, password FROM users')->fetchAll();
    foreach ($legacyUsers as $user) {
        $storedPassword = $user['password'];
        if (empty($storedPassword)) {
            continue;
        }

        $info = password_get_info($storedPassword);
        if ($info['algo'] === 0) {
            $newHash = password_hash($storedPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $update->execute([$newHash, $user['id']]);
        }
    }

    // Ensure a default admin user exists.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute(['admin']);
    $exists = $stmt->fetchColumn();

    if (!$exists) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
        $insert->execute(['admin', $hash, 'admin']);
    }
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
