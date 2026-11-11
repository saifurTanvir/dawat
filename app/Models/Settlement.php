<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** F28: money settled with a cook (payout) or a shahidar (cash handed in). */
class Settlement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    /** F49: the admin who recorded it. */
    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function shahidar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shahidar_id');
    }
}
