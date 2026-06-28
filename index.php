<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <h1 class="display-5 fw-bold text-primary mb-3">Student Attendance Management System</h1>
                <p class="lead text-muted">A secure, responsive capstone platform for managing student attendance, parent communication, and attendance analytics for Bigaa Elementary School.</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="login.php" class="btn btn-primary px-4">Login</a>
                    <a href="#features" class="btn btn-outline-secondary px-4">Learn More</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-lg rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-shield-halved me-2 text-primary"></i>System Highlights</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item ps-0">Secure session-based authentication</li>
                            <li class="list-group-item ps-0">Role-based access for admin, teacher, and parent</li>
                            <li class="list-group-item ps-0">Bootstrap dashboard foundation</li>
                            <li class="list-group-item ps-0">Ready for future facial recognition and mobile app integration</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
