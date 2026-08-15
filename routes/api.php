<?php

use App\Http\Controllers\Api\V1\BiometricPhotoController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public
    Route::post('auth/login', [AuthController::class, 'login']);

    // Device-to-server (recognition node): X-Camera-Id + X-Device-Key
    Route::middleware('device')->group(function () {
        Route::post('attendance/recognitions', [AttendanceController::class, 'recognitions']);
        Route::get('attendance/sessions/open', [AttendanceController::class, 'openSessionsForDevice']);
        Route::get('biometric/approved', [BiometricPhotoController::class, 'approved']);
        Route::get('biometric/photos/{photo}/file', [BiometricPhotoController::class, 'file']);
        Route::post('biometric/submissions/{submission}/synced', [BiometricPhotoController::class, 'markSynced']);
    });

    // Token-authenticated (Flutter app users)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/device-token', [AuthController::class, 'deviceToken']);

        Route::get('students', [StudentController::class, 'index']);
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::get('students/{student}/attendance', [StudentController::class, 'attendance']);

        Route::get('sessions/active', [AttendanceController::class, 'activeSessions']);
        Route::get('attendance', [AttendanceController::class, 'index']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('analytics/student/{student}', [AnalyticsController::class, 'studentSummary']);

        Route::prefix('parent')->group(function () {
            Route::get('dashboard', [ParentController::class, 'dashboard']);
            Route::get('children', [ParentController::class, 'children']);
            Route::get('enrollment-requests', [ParentController::class, 'enrollmentRequests']);
            Route::post('enrollment-requests', [ParentController::class, 'storeEnrollmentRequest']);
            Route::post('notification-preference', [ParentController::class, 'updateNotificationPreference']);
            Route::get('excuse-requests', [ParentController::class, 'excuseRequests']);
            Route::post('excuse-requests', [ParentController::class, 'openExcuseRequest']);
            Route::get('excuse-requests/{excuseRequest}/file/{type}', [ParentController::class, 'excuseLetterFile'])
                ->whereIn('type', ['pdf', 'photo']);
            Route::post('excuse-requests/{excuseRequest}', [ParentController::class, 'submitExcuseLetter']);
            Route::post('biometric-photos', [ParentController::class, 'storeBiometricPhotos']);
        });

        Route::prefix('teacher')->group(function () {
            Route::get('dashboard', [TeacherController::class, 'dashboard']);
            Route::get('attendance', [TeacherController::class, 'attendanceIndex']);
            Route::post('attendance/open', [TeacherController::class, 'openSession']);
            Route::post('recognition/engine', [TeacherController::class, 'updateRecognitionEngine']);
            Route::get('attendance/{session}', [TeacherController::class, 'showSession']);
            Route::post('attendance/{session}/records', [TeacherController::class, 'storeAttendance']);
            Route::post('attendance/{session}/close', [TeacherController::class, 'closeSession']);
            Route::post('attendance/{session}/students/{student}/time-out', [TeacherController::class, 'recordTimeOut']);
            Route::get('enrollment-requests', [TeacherController::class, 'enrollmentRequests']);
            Route::post('enrollment-requests/{enrollmentRequest}/approve', [TeacherController::class, 'approveEnrollmentRequest']);
            Route::post('enrollment-requests/{enrollmentRequest}/reject', [TeacherController::class, 'rejectEnrollmentRequest']);
            Route::get('excuse-requests', [TeacherController::class, 'excuseRequests']);
            Route::get('excuse-requests/{excuseRequest}/file/{type}', [TeacherController::class, 'excuseLetterFile'])
                ->whereIn('type', ['pdf', 'photo']);
            Route::post('excuse-requests/{excuseRequest}/approve', [TeacherController::class, 'approveExcuseRequest']);
            Route::post('excuse-requests/{excuseRequest}/reject', [TeacherController::class, 'rejectExcuseRequest']);
            Route::get('biometric-submissions', [TeacherController::class, 'biometricSubmissions']);
            Route::post('biometric-submissions/{submission}/approve', [TeacherController::class, 'approveBiometricSubmission']);
            Route::post('biometric-submissions/{submission}/reject', [TeacherController::class, 'rejectBiometricSubmission']);
            Route::get('biometric-photos/{photo}/file', [TeacherController::class, 'biometricPhotoFile']);
        });
    });
});
