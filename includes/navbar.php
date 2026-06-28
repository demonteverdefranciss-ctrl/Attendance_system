<?php
$role = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['user_name'] ?? 'User';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#"><i class="fa-solid fa-graduation-cap me-2"></i>Attendance System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item me-3 text-white-50">Signed in as <span class="text-white fw-semibold"><?= htmlspecialchars($userName) ?></span></li>
                <li class="nav-item">
                    <a class="btn btn-outline-light btn-sm" href="<?= $baseUrl ?? '/attendance_system/' ?>logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
