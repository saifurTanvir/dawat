<?php

namespace App\Models;

use App\Casts\AsGeoPoint;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['platform_fee_type', 'platform_fee_value', 'delivery_cut_type', 'delivery_cut_value', 'name', 'slug', 'center', 'radius_m', 'delivery_fee_paisa', 'is_active', 'sort_order'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'center' => AsGeoPoint::class,
            'radius_m' => 'integer',
            'delivery_fee_paisa' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function kitchens(): HasMany
    {
        return $this->hasMany(Kitchen::class);
    }

    /** Shahidars who serve this area (an area is also a delivery zone). */
    public function shahidars(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'area_shahidar')->withTimestamps();
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
