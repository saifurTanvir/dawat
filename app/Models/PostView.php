<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F35: someone saw a post in the feed or opened it. */
#[Fillable(['post_id', 'user_id', 'viewer_key', 'area_id', 'distance_m', 'source', 'viewed_on'])]
class PostView extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['viewed_on' => 'date', 'distance_m' => 'integer'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
