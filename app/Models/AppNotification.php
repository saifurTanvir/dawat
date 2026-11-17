<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/** F66: one notification in a person's in-app list ("নোটিফিকেশন"). */
class AppNotification extends Model
{
    use Prunable;

    protected $guarded = ['id'];

    public const KEEP_DAYS = 60;

    /** Which part of the app a notification is about, by where it opens. */
    public const SECTIONS = [
        '/shahidar' => ['key' => 'shahidar', 'label' => 'শাহিদারের কাজ', 'icon' => '🛵'],
        '/cook' => ['key' => 'cook', 'label' => 'রান্নাঘর', 'icon' => '🍳'],
        '/regular' => ['key' => 'regular', 'label' => 'নিয়মিত খাবার', 'icon' => '🍱'],
        '/orders' => ['key' => 'orders', 'label' => 'আমার অর্ডার', 'icon' => '🛍️'],
        '/history' => ['key' => 'history', 'label' => 'যা পেয়েছি · রিভিউ', 'icon' => '⭐'],
        '/admin' => ['key' => 'admin', 'label' => 'অ্যাডমিন', 'icon' => '🛠️'],
    ];

    public const OTHER = ['key' => 'other', 'label' => 'দাওয়াত', 'icon' => '🔔'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /** @return array{key: string, label: string, icon: string} */
    public static function sectionOf(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        foreach (self::SECTIONS as $prefix => $section) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/') || str_starts_with($path, $prefix.'?')) {
                return $section;
            }
        }

        return self::OTHER;
    }

    public function section(): array
    {
        return self::sectionOf($this->url);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /**
     * Saves a notification for the in-app list. A newer one with the same tag
     * (the same box, request…) replaces an unread older one, like on the phone.
     */
    public static function record(int $userId, string $title, string $body, string $url, ?string $tag): self
    {
        $row = $tag ? self::where('user_id', $userId)->where('tag', $tag)->unread()->latest('id')->first() : null;
        $values = ['title' => mb_substr($title, 0, 200), 'body' => mb_substr($body, 0, 600), 'url' => mb_substr($url ?: '/', 0, 300)];
        if ($row) {
            // Move it to the top of the list.
            $row->delete();
        }

        return self::create(['user_id' => $userId, 'tag' => $tag] + $values);
    }
}
