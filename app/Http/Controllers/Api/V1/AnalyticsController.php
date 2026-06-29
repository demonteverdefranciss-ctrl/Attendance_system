<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends ApiController
{
    public function studentSummary(Request $request, Student $student): JsonResponse
    {
        if (! $request->user()->canAccessStudent($student)) {
            return $this->fail('You cannot access this student.', 'FORBIDDEN', 403);
        }

        $counts = $student->attendanceRecords()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $late = (int) ($counts['late'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);
        $total = $present + $late + $absent + $excused;
        $rate = $total ? round(($present + $late) / $total * 100, 1) : 0;

        return $this->ok([
            'student_id' => $student->id,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'excused' => $excused,
            'total' => $total,
            'attendance_rate' => $rate,
        ]);
    }
}
