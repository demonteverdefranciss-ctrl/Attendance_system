<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = [
        'adviser_id',
        'camera_id',
        'name',
        'grade_level',
        'school_year',
        'session_max_minutes',
    ];

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'adviser_id');
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    /**
     * Minutes an open session should last before auto-close.
     */
    public function sessionMaxMinutes(): int
    {
        $minutes = (int) ($this->session_max_minutes ?? 0);

        return $minutes > 0 ? $minutes : (int) config('attendance.session_max_minutes', 360);
    }
}
