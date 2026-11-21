<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Support\AdminFeatures;
use App\Support\Roles;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'phone', 'phone_verified_at', 'email', 'password', 'status', 'call_from', 'call_to', 'contact_prefer', 'campaign', 'intake_note', 'people_import_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
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
            'order_blocked_until' => 'datetime',
        ];
    }

    /** F37: devices that get Dawat notifications. */
    /** F88: call-list entries this person closed. */
    public function closedCallRequests(): HasMany
    {
        return $this->hasMany(CallRequest::class, 'done_by');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function kitchen(): HasOne
    {
        return $this->hasOne(Kitchen::class);
    }

    /** F25: set when this person is a regular customer. */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** Areas this user serves as a shahidar. */
    /** F63: admins' remarks on this person, newest first. */
    public function remarks(): HasMany
    {
        return $this->hasMany(Remark::class)->latest('id');
    }

    public function latestRemark(): HasOne
    {
        return $this->hasOne(Remark::class)->latestOfMany();
    }

    public function shahidarAreas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_shahidar')->withTimestamps();
    }

    /** F51: times someone else opened this person's app. */
    public function impersonations(): HasMany
    {
        return $this->hasMany(Impersonation::class, 'target_id');
    }

    /** F50: codes sent to this number. */
    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'phone', 'phone');
    }

    /** F49: one-off feed orders this person bought. */
    public function boughtOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    /** F49: cash this shahidar handed to Dawat. */
    public function shahidarSettlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'shahidar_id')->where('kind', 'shahidar');
    }

    /** F49: pairings this shahidar delivers for, past and present. */
    public function shahidarPairings(): HasMany
    {
        return $this->hasMany(Pairing::class, 'shahidar_id');
    }

    /** F49: what this person did for cooks (F46). */
    public function kitchenActions(): HasMany
    {
        return $this->hasMany(KitchenAction::class);
    }

    /** F49: reviews this person gave. */
    public function givenReviews(): HasMany
    {
        return $this->hasMany(BoxReview::class);
    }

    /** F49: reviews of boxes this shahidar delivered. */
    public function deliveredReviews(): HasManyThrough
    {
        return $this->hasManyThrough(BoxReview::class, Order::class, 'shahidar_id', 'order_id');
    }

    /** Boxes this shahidar carries. */
    public function shahidarOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'shahidar_id');
    }

    public function isShahidar(): bool
    {
        return $this->hasRole(Roles::SHAHIDAR);
    }

    /** F29: may use "নিতে চাই" (not blocked, no recent run of no-shows). */
    public function canOrder(): bool
    {
        return ! $this->isBlocked() && ($this->order_blocked_until === null || $this->order_blocked_until->isPast());
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

    /** Only active admins may open /admin. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isTeam() && ! $this->isBlocked();
    }

    /** F69: has a staff role an admin made (any role beyond admin, cook, buyer, shahidar). */
    public function isStaff(): bool
    {
        return $this->roles->contains(fn ($role) => ! in_array($role->name, Roles::all(), true));
    }

    /** F69: Dawat's own people: admins and staff. */
    public function isTeam(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    /** F69: may use this part of the admin panel (see AdminFeatures). */
    public function canUseFeature(string $key): bool
    {
        return $this->isAdmin()
            || $this->getAllPermissions()->contains('name', AdminFeatures::PREFIX.$key);
    }

    public function getFilamentName(): string
    {
        return $this->name ?: $this->phone;
    }
}
