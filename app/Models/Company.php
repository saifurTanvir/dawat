<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** F68: an office whose employees eat Dawat's lunch as regular customers. */
class Company extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** The office's person who takes the drop and gets its code. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function drops(): HasMany
    {
        return $this->hasMany(OfficeDrop::class);
    }
}
