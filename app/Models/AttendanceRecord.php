<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'session_id',
        'student_id',
        'status',
        'time_in',
        'method',
        'confidence',
        'camera_id',
        'marked_by',
        'client_uuid',
    ];

    protected function casts(): array
    {
        return [
            'time_in' => 'datetime',
            'confidence' => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
