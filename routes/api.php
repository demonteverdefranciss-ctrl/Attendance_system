<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public
    Route::post('auth/login', [AuthController::class, 'login']);

    // Device-to-server (recognition node): X-Camera-Id + X-Device-Key
    Route::middleware('device')->post('attendance/recognitions', [AttendanceController::class, 'recognitions']);

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
            Route::get('enrollment-requests', [ParentController::class, 'enrollmentRequests']);
            Route::post('enrollment-requests', [ParentController::class, 'storeEnrollmentRequest']);
            Route::post('notification-preference', [ParentController::class, 'updateNotificationPreference']);
        });
    });
});
