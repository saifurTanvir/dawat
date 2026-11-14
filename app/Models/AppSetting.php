<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** F56: small settings admins change from the panel, by key. */
#[Fillable(['key', 'value', 'updated_by'])]
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, array $default = []): array
    {
        return Cache::remember('app-setting:'.$key, 300, fn () => static::find($key)?->value ?? $default);
    }

    public static function put(string $key, array $value, ?User $by = null): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by?->id]);
        Cache::forget('app-setting:'.$key);
    }
}
