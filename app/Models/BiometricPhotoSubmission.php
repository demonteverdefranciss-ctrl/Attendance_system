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
    ];

    protected function casts(): array
    {
        return [
            'consent_acknowledged' => 'boolean',
            'reviewed_at' => 'datetime',
            'synced_at' => 'datetime',
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

    public function photos(): HasMany
    {
        return $this->hasMany(BiometricPhoto::class, 'submission_id');
    }
}
