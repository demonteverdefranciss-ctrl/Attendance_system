<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildEnrollmentRequest extends Model
{
    protected $fillable = [
        'guardian_id',
        'student_id',
        'lrn',
        'first_name',
        'last_name',
        'gender',
        'grade_level',
        'relationship',
        'status',
        'teacher_id',
        'notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function getFullNameAttribute(): string
    {
        if ($this->student) {
            return $this->student->full_name;
        }

        return trim("{$this->first_name} {$this->last_name}");
    }
}
