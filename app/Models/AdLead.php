<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** F93: one person who came from a Badda ad on WhatsApp or by commenting. */
class AdLead extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_at' => 'datetime', 'replied_at' => 'datetime'];
    }
}
