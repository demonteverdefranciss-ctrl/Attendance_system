<?php

namespace App\Models;

use App\Casts\EncryptedEmbedding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceData extends Model
{
    protected $table = 'face_data';

    protected $fillable = [
        'student_id',
        'embedding',
        'lbph_label',
        'image_path',
        'model_version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => EncryptedEmbedding::class,
            'is_active' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
