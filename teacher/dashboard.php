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
                            <h5 class="card-title">Today's Attendance</h5>
                            <p class="text-muted mb-0">View and update pupil attendance for the current session.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">Quick Actions</h5>
                            <ul class="mb-0 ps-3">
                                <li>View student list</li>
                                <li>Mark attendance</li>
                                <li>Review attendance reports</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
