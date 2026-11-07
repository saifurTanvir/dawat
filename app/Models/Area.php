<?php

namespace App\Models;

use App\Casts\AsGeoPoint;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'center', 'radius_m', 'is_active', 'sort_order'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'center' => AsGeoPoint::class,
            'radius_m' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function kitchens(): HasMany
    {
        return $this->hasMany(Kitchen::class);
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
