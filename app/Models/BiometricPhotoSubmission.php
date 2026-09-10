<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricPhotoSubmission extends Model
{
    protected $fillable = [
        'student_id',
        'guardian_id',
        'status',
        'consent_acknowledged',
        'teacher_id',
        'reviewed_at',
        'synced_at',
        'notes',
        'system_validated_at',
        'validation_summary',
    ];

    protected function casts(): array
    {
        return [
            'consent_acknowledged' => 'boolean',
            'reviewed_at' => 'datetime',
            'synced_at' => 'datetime',
            'system_validated_at' => 'datetime',
            'validation_summary' => 'array',
        ];
    }

    public function enrollmentStatus(): string
    {
        if ($this->status === 'rejected') {
            return 'rejected';
        }
        if ($this->status === 'pending') {
            return 'pending';
        }
        if ($this->status === 'approved' && $this->synced_at) {
            return 'active';
        }

        return $this->status;
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

    public function photos(): HasMany
    {
        return $this->hasMany(BiometricPhoto::class, 'submission_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function parentPayload(): array
    {
        return [
            'status' => $this->status,
            'enrollment_status' => $this->enrollmentStatus(),
            'system_validated' => $this->system_validated_at !== null,
            'created_at' => $this->created_at?->toDateTimeString(),
            'reviewed_at' => $this->reviewed_at?->toDateTimeString(),
            'notes' => $this->notes,
        ];
    }
}
