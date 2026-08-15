<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Whether this device may mark a student in the given section.
     *
     * Dedicated cameras (with assigned sections) only cover those sections.
     * Unassigned cameras stay as a shared fallback for sections with no camera.
     */
    public function coversSection(int $sectionId, ?int $sectionCameraId = null): bool
    {
        $this->loadMissing('sections:id,camera_id');
        $assignedIds = $this->sections->pluck('id');

        if ($assignedIds->isNotEmpty()) {
            return $assignedIds->contains($sectionId);
        }

        return $sectionCameraId === null;
    }
}
