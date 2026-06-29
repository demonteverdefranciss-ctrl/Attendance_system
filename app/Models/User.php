<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'username',
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    /**
     * Check whether the user has one of the given role name(s).
     *
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->role && in_array($this->role->name, $roles, true);
    }

    /**
     * Authorization: can this user view the given student?
     * admin → any; teacher → students in their sections; parent → their children.
     */
    public function canAccessStudent(Student $student): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->hasRole('teacher')) {
            return $this->teacher
                && $student->section_id
                && $this->teacher->sections()->whereKey($student->section_id)->exists();
        }

        if ($this->hasRole('parent')) {
            return $this->guardian
                && $this->guardian->students()->whereKey($student->id)->exists();
        }

        return false;
    }
}
