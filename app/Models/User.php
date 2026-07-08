<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Support\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password_hash
 * @property Role $role
 * @property string $theme_preference
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'theme_preference',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * MediaForge stores the hash in `password_hash` (core-schema.md), not the
     * Laravel-default `password` column.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'password_hash' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function hasRole(Role $role): bool
    {
        return $this->role->atLeast($role);
    }
}
