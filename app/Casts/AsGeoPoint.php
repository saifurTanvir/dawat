<?php

namespace App\Casts;

use App\Support\Geo\GeoPoint;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a MySQL POINT SRID 4326 column to and from a GeoPoint.
 *
 * @implements CastsAttributes<GeoPoint|null, GeoPoint|array|null>
 */
class AsGeoPoint implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?GeoPoint
    {
        if ($value === null || $value instanceof Expression) {
            return null;
        }

        return GeoPoint::fromMysqlBinary($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = GeoPoint::make($value['lat'] ?? throw new InvalidArgumentException('lat missing'), $value['lng'] ?? throw new InvalidArgumentException('lng missing'));
        }

        if (! $value instanceof GeoPoint) {
            throw new InvalidArgumentException("{$key} must be a GeoPoint or [lat, lng] array.");
        }

        return $value->toExpression();
    }
}
