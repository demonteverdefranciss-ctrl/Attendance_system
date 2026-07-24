<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceExcuseRequest extends Model
{
    protected $fillable = [
        'student_id',
        'guardian_id',
        'streak_count',
        'attendance_record_ids',
        'streak_summary',
        'status',
        'letter_body',
        'teacher_id',
        'notes',
        'notified_at',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_record_ids' => 'array',
            'streak_summary' => 'array',
            'notified_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
