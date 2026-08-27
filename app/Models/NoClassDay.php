<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoClassDay extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_GOOGLE = 'google';

    protected $fillable = [
        'date',
        'name',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
