<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NoClassDay extends Model
{
    use SoftDeletes;
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
