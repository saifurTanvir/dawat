<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['phone', 'code_hash', 'expires_at', 'ip', 'channel', 'created_by'])]
class OtpCode extends Model
{
    use MassPrunable;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    /** Old codes are deleted daily by model:prune. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }
}
