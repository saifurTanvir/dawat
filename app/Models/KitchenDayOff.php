<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** F43: "আজ রান্না নেই" for one meal; the daily menu skips it. */
class KitchenDayOff extends Model
{
    protected $table = 'kitchen_days_off';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
