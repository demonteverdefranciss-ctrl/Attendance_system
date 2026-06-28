<?php
$role = $_SESSION['role'] ?? 'guest';
$baseUrl = '/attendance_system/';
?>
<aside class="col-md-3 col-lg-2 d-md-block sidebar collapse bg-dark text-white">
    <div class="position-sticky pt-3">
        <div class="px-3 py-4 border-bottom border-secondary">
            <h5 class="mb-0"><i class="fa-solid fa-school me-2"></i>Attendance System</h5>
            <small class="text-white-50">Capstone Project</small>
        </div>
        <ul class="nav flex-column px-2 py-3">
            <?php if ($role === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="<?= $baseUrl ?>admin/dashboard.php"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a>
                </li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-chalkboard-user me-2"></i>Manage Teachers</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-users me-2"></i>Manage Students</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-user-group me-2"></i>Manage Parents</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-chart-line me-2"></i>Analytics</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-bell me-2"></i>Notifications</a></li>
            <?php elseif ($role === 'teacher'): ?>
                <li class="nav-item"><a class="nav-link text-white" href="<?= $baseUrl ?>teacher/dashboard.php"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-users me-2"></i>Students</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-clipboard-check me-2"></i>Attendance</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-chart-column me-2"></i>Reports</a></li>
            <?php elseif ($role === 'parent'): ?>
                <li class="nav-item"><a class="nav-link text-white" href="<?= $baseUrl ?>parent/dashboard.php"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-child me-2"></i>Child Attendance</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="#"><i class="fa-solid fa-bell me-2"></i>Notifications</a></li>
            <?php endif; ?>
        </ul>
    </div>
</aside>
