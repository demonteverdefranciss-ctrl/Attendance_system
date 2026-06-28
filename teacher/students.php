<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
include '../includes/header.php';
include '../includes/navbar.php';

// Get all students
$students = $pdo->query('SELECT student_id, student_name, section, parent_name, created_at FROM student ORDER BY student_name')->fetchAll();
?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Students List</h1>
                    <p class="text-muted mb-0">View all registered students</p>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <div class="alert alert-warning">No students found yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Student Name</th>
                                        <th>Section</th>
                                        <th>Parent Name</th>
                                        <th>Registered</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><span class="badge bg-primary"><?= htmlspecialchars($student['student_id']) ?></span></td>
                                            <td><?= htmlspecialchars($student['student_name']) ?></td>
                                            <td><?= htmlspecialchars($student['section'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($student['parent_name'] ?? '-') ?></td>
                                            <td><small class="text-muted"><?= date('M d, Y', strtotime($student['created_at'])) ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
