<?php

namespace App\Models;

use App\Enums\TagType;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'type', 'sort_order', 'is_active'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => TagType::class,
            'is_active' => 'boolean',
        ];
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
