<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherNotification extends Model
{
    protected $fillable = [
        'teacher_id',
        'type',
        'title',
        'body',
        'payload',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
