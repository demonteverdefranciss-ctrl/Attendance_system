<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
include '../includes/header.php';
include '../includes/navbar.php';

$totalStudents = $pdo->query('SELECT COUNT(*) FROM student')->fetchColumn();
$totalTeachers = $pdo->query('SELECT COUNT(*) FROM teacher')->fetchColumn();
$totalParents = $pdo->query('SELECT COUNT(*) FROM parent')->fetchColumn();
$presentToday = $pdo->query("SELECT COUNT(*) FROM attendance_records WHERE attendance_date = CURDATE() AND status = 'Present'")->fetchColumn();
$absentToday = $pdo->query("SELECT COUNT(*) FROM attendance_records WHERE attendance_date = CURDATE() AND status = 'Absent'")->fetchColumn();
?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Admin Dashboard</h1>
                    <p class="text-muted mb-0">Overview of attendance operations and records.</p>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Students</h6>
                                    <h3 class="fw-bold mb-0"><?= $totalStudents ?></h3>
                                </div>
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary"><i class="fa-solid fa-users"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Teachers</h6>
                                    <h3 class="fw-bold mb-0"><?= $totalTeachers ?></h3>
                                </div>
                                <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success"><i class="fa-solid fa-chalkboard-user"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Total Parents</h6>
                                    <h3 class="fw-bold mb-0"><?= $totalParents ?></h3>
                                </div>
                                <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning"><i class="fa-solid fa-user-group"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1">Present Today</h6>
                                    <h3 class="fw-bold mb-0"><?= $presentToday ?></h3>
                                </div>
                                <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info"><i class="fa-solid fa-check"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Quick Access</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="students/" class="text-decoration-none text-dark">
                                <div class="border rounded p-3 h-100"><i class="fa-solid fa-users me-2 text-primary"></i>Manage Students</div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="teachers/" class="text-decoration-none text-dark">
                                <div class="border rounded p-3 h-100"><i class="fa-solid fa-chalkboard-user me-2 text-success"></i>Manage Teachers</div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="parents/" class="text-decoration-none text-dark">
                                <div class="border rounded p-3 h-100"><i class="fa-solid fa-user-group me-2 text-warning"></i>Manage Parents</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
