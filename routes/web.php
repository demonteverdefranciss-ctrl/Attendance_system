<?php

use App\Http\Controllers\Admin\GuardianController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ParentRegistrationController;
use App\Http\Controllers\Teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Guest-only authentication routes.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('register/parent', [ParentRegistrationController::class, 'create'])->name('register.parent');
    Route::post('register/parent', [ParentRegistrationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.parent.store');
});

// Authenticated routes.
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Role dispatcher: sends each user to their own dashboard.
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'admin'])->name('dashboard');

        Route::resource('teachers', TeacherController::class)->except('show');
        Route::resource('guardians', GuardianController::class)->except('show');
        Route::resource('sections', SectionController::class)->except('show');
        Route::resource('students', StudentController::class)->except('show');
        Route::resource('schedules', ScheduleController::class)->except('show');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    Route::middleware('role:teacher')->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'teacher'])->name('dashboard');
        Route::get('enrollment-requests', [DashboardController::class, 'teacherEnrollmentRequests'])->name('enrollment-requests.index');
        Route::post('enrollment-requests/{enrollmentRequest}/approve', [DashboardController::class, 'approveEnrollmentRequest'])
            ->name('enrollment-requests.approve');
        Route::post('enrollment-requests/{enrollmentRequest}/reject', [DashboardController::class, 'rejectEnrollmentRequest'])
            ->name('enrollment-requests.reject');

        Route::get('attendance', [TeacherAttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance/open', [TeacherAttendanceController::class, 'open'])->name('attendance.open');
        Route::get('attendance/{session}', [TeacherAttendanceController::class, 'show'])->name('attendance.show');
        Route::post('attendance/{session}', [TeacherAttendanceController::class, 'store'])->name('attendance.store');
        Route::post('attendance/{session}/students/{student}/time-out', [TeacherAttendanceController::class, 'recordTimeOut'])
            ->name('attendance.time-out');
        Route::post('attendance/{session}/close', [TeacherAttendanceController::class, 'close'])->name('attendance.close');
    });

    Route::middleware('role:parent')->prefix('parent')->name('parent.')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'parent'])->name('dashboard');
        Route::post('enrollment-requests', [DashboardController::class, 'createEnrollmentRequest'])->name('enrollment-requests.store');
        Route::post('notifications/{notification}/read', [DashboardController::class, 'markParentNotificationRead'])
            ->name('notifications.read');
        Route::post('notifications/preferences', [DashboardController::class, 'updateParentNotificationPreference'])
            ->name('notifications.preferences');
    });

    // Reports & exports (admin sees all sections; teacher sees only their own).
    Route::middleware('role:admin,teacher')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/csv', [ReportController::class, 'csv'])->name('reports.csv');
        Route::get('reports/export/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    });
});
