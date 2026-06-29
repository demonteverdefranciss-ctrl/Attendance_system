<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camera extends Model
{
    protected $fillable = [
        'name',
        'location',
        'rtsp_url',
        'api_key_hash',
        'is_active',
    ];

    protected $hidden = [
        'api_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
