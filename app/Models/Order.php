<?php

namespace App\Models;

use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Services\Money\CookFees;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** One box for one regular customer on one day and meal (F26). */
class Order extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['delivery_code_hash'];

    /** F56: the cook's fees are fixed on each box when it is made or its food changes. */
    protected static function booted(): void
    {
        static::saving(function (Order $order) {
            if (! $order->exists || $order->isDirty(['food_paisa', 'plates', 'payment_method', 'receive', 'kitchen_id'])) {
                app(CookFees::class)->stamp($order);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'meal_slot' => MealSlot::class,
            'status' => OrderStatus::class,
            'picked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'paid_at' => 'datetime',
            'ask_until' => 'datetime',
            'ready_at' => 'datetime',
        ];
    }

    /** F39: the bill this box was charged on. */
    /** F47: the customer's stars for this box. */
    public function review(): HasOne
    {
        return $this->hasOne(BoxReview::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** F29: who booked a one-off box from the feed. */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function isOneOff(): bool
    {
        return $this->kind === 'oneoff';
    }

    public function isSelfPickup(): bool
    {
        return $this->receive === 'self';
    }

    /** The person the box is for: a regular customer or a one-off buyer. */
    public function recipient(): User
    {
        return $this->isOneOff() ? $this->buyer : $this->customer->user;
    }

    public function recipientAddress(): ?string
    {
        return $this->isOneOff() ? $this->delivery_address : $this->customer->address;
    }

    public function recipientNote(): ?string
    {
        return $this->isOneOff() ? $this->buyer_note : $this->customer->note;
    }

    public function shahidar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shahidar_id');
    }

    public function sticker(): HasOne
    {
        return $this->hasOne(Sticker::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [OrderStatus::Asking, OrderStatus::Booked, OrderStatus::PickedUp]);
    }

    public function totalPaisa(): int
    {
        return $this->food_paisa + $this->delivery_fee_paisa;
    }

    /** The box code people say on the phone and write on a box: ০৮-৩৭. */
    public function code(): string
    {
        return self::banglaDigits($this->service_date->format('d').'-'.$this->seq);
    }

    public static function banglaDigits(string|int $value): string
    {
        return strtr((string) $value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
