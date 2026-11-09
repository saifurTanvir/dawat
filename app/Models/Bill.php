<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** F39: a customer's bill for a week or a month of boxes. */
class Bill extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token'];

    public const STATUS_LABELS = ['open' => 'বাকি', 'partial' => 'আংশিক পরিশোধ', 'paid' => 'পরিশোধিত', 'void' => 'বাতিল'];

    public const METHOD_LABELS = ['cash' => 'নগদ', 'bkash' => 'বিকাশ', 'other' => 'অন্যভাবে'];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'due_on' => 'date',
            'paid_at' => 'datetime',
            'orders_count' => 'integer',
            'food_paisa' => 'integer',
            'delivery_paisa' => 'integer',
            'total_paisa' => 'integer',
            'paid_paisa' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('service_date')->orderBy('meal_slot');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class)->latest('id');
    }

    public function duePaisa(): int
    {
        return max(0, $this->total_paisa - $this->paid_paisa);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function publicUrl(): string
    {
        return url('/bill/'.$this->token);
    }
}
