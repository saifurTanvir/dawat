<?php

namespace App\Models;

use App\Casts\AsGeoPoint;
use App\Enums\MealSlot;
use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'kitchen_id', 'area_id', 'title', 'price_paisa', 'plates_total', 'plates_left',
    'meal_slot', 'note', 'available_until', 'location', 'status', 'published_at', 'reach_m', 'boosted', 'source', 'posted_by',
])]
#[Hidden(['location'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'location' => AsGeoPoint::class,
            'status' => PostStatus::class,
            'meal_slot' => MealSlot::class,
            'price_paisa' => 'integer',
            'plates_total' => 'integer',
            'plates_left' => 'integer',
            'available_until' => 'datetime',
            'published_at' => 'datetime',
            'preorder_for_date' => 'date',
            'reach_m' => 'integer',
            'boosted' => 'boolean',
        ];
    }

    /** F26: regular customers' boxes from this post. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** F43: who posted it, when not the cook herself. */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class)->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function contactEvents(): HasMany
    {
        return $this->hasMany(ContactEvent::class);
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /** F35: whole areas where this post also shows, besides its own surroundings. */
    public function reachAreas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'post_reach_areas');
    }

    /** F35: who saw it (one row per viewer per day). */
    public function views(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    /** F35: set a wider reach; null and [] mean the normal, buyer-chosen distance. */
    public function setReach(?int $reachM, array $areaIds): void
    {
        $this->reachAreas()->sync($areaIds);
        $this->forceFill(['reach_m' => $reachM ?: null, 'boosted' => (bool) $reachM || $areaIds !== []])->save();
    }

    /** Posts a buyer can see right now. */
    public function scopeVisibleNow(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Live)
            ->where('available_until', '>', now())
            ->where('plates_left', '>', 0);
    }

    public function shareImageUrl(): ?string
    {
        return $this->share_image_path
            ? Storage::disk(config('filesystems.media_disk'))->url($this->share_image_path)
            : null;
    }

    public function priceTaka(): float
    {
        return $this->price_paisa / 100;
    }
}
