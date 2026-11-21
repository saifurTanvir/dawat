<?php

namespace App\Models;

use App\Casts\AsGeoPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** F68: one cook's "drop your home pin" link. Admins only see the pin. */
class PinLink extends Model
{
    public const DAYS = 7;

    protected $guarded = ['id'];

    protected $hidden = ['submitted_location'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'flagged' => 'boolean',
            'submitted_location' => AsGeoPoint::class,
        ];
    }

    /** Reuses her still-valid link, else makes a new one. */
    public static function forKitchen(Kitchen $kitchen, ?User $by = null): self
    {
        return self::query()->where('kitchen_id', $kitchen->id)->where('expires_at', '>', now()->addDay())->latest('id')->first()
            ?? self::create([
                'token' => Str::random(40),
                'kitchen_id' => $kitchen->id,
                'created_by' => $by?->id,
                'expires_at' => now()->addDays(self::DAYS),
            ]);
    }

    /** F76: kills every still-open link of this kitchen and makes a fresh one (for a link sent to the wrong person). */
    public static function renewForKitchen(Kitchen $kitchen, ?User $by = null): self
    {
        self::query()->where('kitchen_id', $kitchen->id)->where('expires_at', '>', now())->update(['expires_at' => now()->subSecond()]);

        return self::forKitchen($kitchen, $by);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function url(): string
    {
        return route('pin.show', $this->token);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Far-off pins waiting for an admin. */
    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('flagged', true)->whereNull('review');
    }

    /** The message that goes with the link on WhatsApp/Messenger. */
    public function message(): string
    {
        $name = $this->kitchen?->user?->name;

        return trim(($name ? $name.' আপু, ' : 'আপু, ').'আপনার বাসার লোকেশনটা দিন 🙏')."\n"
            .'আপনার রান্নাঘর থেকে আধা থেকে ১ কিলোমিটারের মধ্যে যে গ্রাহক আছেন, শুধু তাঁদেরই আপনার সাথে জুড়ে দেওয়া হয়, আর আমাদের এলাকা ম্যানেজার আপনার বাসার গেট থেকে বক্স আনেন। '
            .'বাসার লোকেশন না পেলে আপনাকে গ্রাহক দিতে পারব না, খাবারও আনতে পারব না — তাই এটা ছাড়া অনবোর্ড শেষ হয় না।'."\n"
            .'লোকেশন কোনো গ্রাহক দেখবেন না। এই লিংকে চেপে ১ মিনিটে দিন:'."\n".$this->url();
    }
}
