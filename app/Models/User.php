<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Support\Roles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'phone', 'phone_verified_at', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** Roles are always checked against the web guard, whichever guard authenticated the request. */
    protected string $guard_name = 'web';

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function kitchen(): HasOne
    {
        return $this->hasOne(Kitchen::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Blocked;
    }

    public function isCook(): bool
    {
        return $this->hasRole(Roles::COOK);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Roles::ADMIN);
    }
}
