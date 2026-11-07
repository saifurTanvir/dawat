<?php

namespace App\Support\Geo;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A WGS84 latitude/longitude pair, stored in MySQL as POINT SRID 4326.
 */
final class GeoPoint implements JsonSerializable
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
    ) {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidArgumentException("Invalid coordinates: {$lat}, {$lng}");
        }
    }

    public static function make(float|string $lat, float|string $lng): self
    {
        return new self((float) $lat, (float) $lng);
    }

    /**
     * Parse MySQL's internal geometry value: 4-byte SRID followed by WKB.
     * For SRID 4326 MySQL stores longitude as X and latitude as Y.
     */
    public static function fromMysqlBinary(string $value): self
    {
        if (strlen($value) < 25) {
            throw new InvalidArgumentException('Not a MySQL POINT value.');
        }

        $littleEndian = ord($value[4]) === 1;
        $format = $littleEndian ? 'e2' : 'E2';
        $coords = unpack($format, substr($value, 9, 16));

        return new self(lat: $coords[2], lng: $coords[1]);
    }

    /** SQL expression that builds this point; safe because both parts are floats. */
    public function toExpression(): Expression
    {
        return DB::raw(sprintf(
            "ST_GeomFromText('POINT(%F %F)', 4326, 'axis-order=long-lat')",
            $this->lng,
            $this->lat,
        ));
    }

    /** Great-circle distance in metres (same formula MySQL's ST_Distance_Sphere uses). */
    public function distanceTo(self $other): float
    {
        $earthRadius = 6370986.0;
        $dLat = deg2rad($other->lat - $this->lat);
        $dLng = deg2rad($other->lng - $this->lng);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->lat)) * cos(deg2rad($other->lat)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(min(1, sqrt($a)));
    }

    /** @return array{lat: float, lng: float} */
    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
