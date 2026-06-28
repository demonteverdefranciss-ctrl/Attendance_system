<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
include '../includes/header.php';
include '../includes/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h1 class="h3 fw-bold mb-1">Teacher Dashboard</h1>
            <p class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Teacher') ?>.</p>
            <div class="row g-4 mt-2">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-clipboard me-2 text-primary"></i>Mark Attendance</h5>
                            <p class="text-muted mb-3">Record daily student attendance</p>
                            <a href="mark_attendance.php" class="btn btn-primary btn-sm">Go to Attendance</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-chart-bar me-2 text-success"></i>View Reports</h5>
                            <p class="text-muted mb-3">Review attendance history and statistics</p>
                            <a href="reports.php" class="btn btn-success btn-sm">View Reports</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fa-solid fa-users me-2 text-info"></i>Student List</h5>
                            <p class="text-muted mb-3">View all registered students</p>
                            <a href="students.php" class="btn btn-info btn-sm">View Students</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
