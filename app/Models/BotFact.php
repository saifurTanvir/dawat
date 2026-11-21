<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * F82: one thing the chat bots may tell people ("বটের জ্ঞান" in the panel).
 * Any bot — Messenger now, WhatsApp later — gets the whole list from
 * BotFact::promptText(). Update the list whenever a feature changes what we tell people.
 */
#[Fillable(['topic', 'body', 'audience', 'feature', 'is_active', 'sort_order'])]
class BotFact extends Model
{
    public const CACHE = 'bot-facts:prompt';

    public const AUDIENCES = [
        'all' => 'সবার জন্য',
        'cook' => 'গৃহিণী',
        'customer' => 'ক্রেতা',
        'shahidar' => 'এলাকা ম্যানেজার',
    ];

    private const AUDIENCE_EN = ['all' => 'anyone', 'cook' => 'cooks', 'customer' => 'customers', 'shahidar' => 'delivery (shahidar) applicants'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::CACHE);
        static::saved($forget);
        static::deleted($forget);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** The active list as bullet lines for a bot's instructions. */
    public static function promptText(): string
    {
        return Cache::rememberForever(self::CACHE, function () {
            $lines = static::query()->active()->get()
                ->map(fn (self $f) => '- ['.(self::AUDIENCE_EN[$f->audience] ?? 'anyone').'] '.trim($f->topic).': '.preg_replace('/\s+/u', ' ', trim($f->body)));

            return $lines->isEmpty()
                ? '- (No facts are listed yet: answer only how to join — name, area, mobile — and set needs_human for anything else.)'
                : $lines->implode("\n");
        });
    }
}
