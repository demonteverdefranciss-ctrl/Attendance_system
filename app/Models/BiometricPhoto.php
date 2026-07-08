<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricPhoto extends Model
{
    protected $fillable = [
        'submission_id',
        'storage_path',
        'original_name',
        'sort_order',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(BiometricPhotoSubmission::class, 'submission_id');
    }
}
