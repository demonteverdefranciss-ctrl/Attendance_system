<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'parent') {
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
            <h1 class="h3 fw-bold mb-1">Parent Dashboard</h1>
            <p class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Parent') ?>.</p>
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <h5 class="card-title">Child Attendance Overview</h5>
                    <p class="text-muted mb-0">Track your child’s attendance history and recent notifications.</p>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
