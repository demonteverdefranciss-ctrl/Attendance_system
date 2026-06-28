<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
include '../includes/header.php';
include '../includes/navbar.php';

$message = '';
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_data = $_POST['attendance'] ?? [];
    $date = $_POST['attendance_date'] ?? date('Y-m-d');
    
    try {
        foreach ($attendance_data as $student_id => $status) {
            if (!empty($status)) {
                // Check if record exists
                $check = $pdo->prepare('SELECT id FROM attendance_records WHERE student_id = ? AND attendance_date = ?');
                $check->execute([$student_id, $date]);
                $exists = $check->fetch();
                
                if ($exists) {
                    $update = $pdo->prepare('UPDATE attendance_records SET status = ? WHERE student_id = ? AND attendance_date = ?');
                    $update->execute([$status, $student_id, $date]);
                } else {
                    $insert = $pdo->prepare('INSERT INTO attendance_records (student_id, attendance_date, status, marked_by) VALUES (?, ?, ?, ?)');
                    $insert->execute([$student_id, $date, $status, $_SESSION['user_id']]);
                }
            }
        }
        $message = '✓ Attendance saved successfully!';
    } catch (Exception $e) {
        $message = '✗ Error saving attendance: ' . $e->getMessage();
    }
}

// Get all students
$students = $pdo->query('SELECT student_id, student_name, section FROM student ORDER BY student_name')->fetchAll();

// Get attendance for selected date
$attendance_records = [];
if (!empty($students)) {
    $stmt = $pdo->prepare('SELECT student_id, status FROM attendance_records WHERE attendance_date = ?');
    $stmt->execute([$selectedDate]);
    $records = $stmt->fetchAll();
    foreach ($records as $record) {
        $attendance_records[$record['student_id']] = $record['status'];
    }
}
?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1">Mark Attendance</h1>
                    <p class="text-muted mb-0">Record daily student attendance</p>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="attendance_date" class="form-label fw-semibold">Select Date</label>
                                <input type="date" id="attendance_date" name="attendance_date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
                            </div>
                        </div>

                        <?php if (empty($students)): ?>
                            <div class="alert alert-warning">No students found. Add students from admin panel first.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40%;">Student Name</th>
                                            <th style="width: 30%;">Section</th>
                                            <th style="width: 30%;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($student['student_name']) ?></td>
                                                <td><?= htmlspecialchars($student['section'] ?? '-') ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <input type="radio" class="btn-check" name="attendance[<?= $student['student_id'] ?>]" id="present_<?= $student['student_id'] ?>" value="Present" <?= ($attendance_records[$student['student_id']] ?? '') === 'Present' ? 'checked' : '' ?>>
                                                        <label class="btn btn-outline-success btn-sm" for="present_<?= $student['student_id'] ?>">Present</label>
                                                        
                                                        <input type="radio" class="btn-check" name="attendance[<?= $student['student_id'] ?>]" id="absent_<?= $student['student_id'] ?>" value="Absent" <?= ($attendance_records[$student['student_id']] ?? '') === 'Absent' ? 'checked' : '' ?>>
                                                        <label class="btn btn-outline-danger btn-sm" for="absent_<?= $student['student_id'] ?>">Absent</label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3">Save Attendance</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
