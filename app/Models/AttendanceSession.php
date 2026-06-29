<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = [
        'section_id',
        'schedule_id',
        'session_date',
        'opened_at',
        'closed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
