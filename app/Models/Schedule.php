<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'section_id',
        'day_of_week',
        'start_time',
        'end_time',
        'late_after',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
