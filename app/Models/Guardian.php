<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Guardian extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'fcm_token',
        'notify_pref',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian')
            ->withPivot('relationship', 'is_primary');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
