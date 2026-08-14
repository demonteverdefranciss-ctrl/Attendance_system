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
        'letter_pdf_path',
        'letter_pdf_name',
        'photo_path',
        'photo_name',
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

    /**
     * @return array{has_pdf: bool, letter_pdf_name: ?string, has_photo: bool, photo_name: ?string}
     */
    public function attachmentMeta(): array
    {
        return [
            'has_pdf' => filled($this->letter_pdf_path),
            'letter_pdf_name' => $this->letter_pdf_name,
            'has_photo' => filled($this->photo_path),
            'photo_name' => $this->photo_name,
        ];
    }

    public function isRequired(): bool
    {
        return (int) $this->streak_count >= 3;
    }
}
