<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
include '../includes/header.php';
include '../includes/navbar.php';

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_student = $_GET['student_id'] ?? '';

// Build query
$query = 'SELECT a.*, s.student_name, s.section 
          FROM attendance_records a 
          JOIN student s ON a.student_id = s.student_id 
          WHERE a.attendance_date BETWEEN ? AND ?';
$params = [$start_date, $end_date];

if (!empty($filter_student)) {
    $query .= ' AND a.student_id = ?';
    $params[] = $filter_student;
}

$query .= ' ORDER BY a.attendance_date DESC, s.student_name ASC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Get student list for filter
$students = $pdo->query('SELECT student_id, student_name FROM student ORDER BY student_name')->fetchAll();

// Calculate summary
$present_count = 0;
$absent_count = 0;
foreach ($records as $record) {
    if ($record['status'] === 'Present') $present_count++;
    else $absent_count++;
}
?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Attendance Reports</h1>
                    <p class="text-muted mb-0">View attendance history and statistics</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="student_id" class="form-label">Filter by Student</label>
                            <select id="student_id" name="student_id" class="form-select">
                                <option value="">All Students</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?= $s['student_id'] ?>" <?= $filter_student == $s['student_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['student_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-success bg-opacity-10">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Present</h6>
                            <h2 class="text-success fw-bold"><?= $present_count ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-danger bg-opacity-10">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Absent</h6>
                            <h2 class="text-danger fw-bold"><?= $absent_count ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-info bg-opacity-10">
                        <div class="card-body">
                            <h6 class="text-muted mb-1">Total Records</h6>
                            <h2 class="text-info fw-bold"><?= count($records) ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($records)): ?>
                        <div class="alert alert-warning">No attendance records found for the selected period.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Student Name</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($record['attendance_date'])) ?></td>
                                            <td><?= htmlspecialchars($record['student_name']) ?></td>
                                            <td><?= htmlspecialchars($record['section'] ?? '-') ?></td>
                                            <td>
                                                <?php if ($record['status'] === 'Present'): ?>
                                                    <span class="badge bg-success">Present</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Absent</span>
                                                <?php endif; ?>
                                            </td>
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
