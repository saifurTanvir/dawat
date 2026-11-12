<?php

namespace App\Models;

use App\Enums\ShahidarApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** F53: someone who asked to work as a shahidar. */
#[Fillable([
    'name', 'phone', 'gender', 'age', 'lives_in', 'job', 'job_note', 'vehicle', 'has_license',
    'has_smartphone', 'has_nid', 'hours', 'slots', 'days_per_week', 'experience', 'heard_from', 'note',
    'status', 'admin_note', 'handled_by', 'handled_at', 'user_id',
])]
class ShahidarApplication extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ShahidarApplicationStatus::class,
            'slots' => 'array',
            'has_license' => 'boolean',
            'has_smartphone' => 'boolean',
            'has_nid' => 'boolean',
            'age' => 'integer',
            'days_per_week' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'shahidar_application_area');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ShahidarApplicationStatus::New, ShahidarApplicationStatus::Contacted]);
    }
}
