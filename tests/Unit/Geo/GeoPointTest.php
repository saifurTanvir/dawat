<?php

namespace Tests\Unit\Geo;

use App\Support\Geo\GeoPoint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GeoPointTest extends TestCase
{
    public function test_it_parses_mysql_internal_point_format(): void
    {
        // Bytes MySQL 8 returned for POINT(90.3654 23.8069) SRID 4326.
        $binary = hex2bin('E61000000101000000567DAEB662975640742497FF90CE3740');

        $point = GeoPoint::fromMysqlBinary($binary);

        $this->assertEqualsWithDelta(23.8069, $point->lat, 1e-9);
        $this->assertEqualsWithDelta(90.3654, $point->lng, 1e-9);
    }

    public function test_distance_matches_mysql_st_distance_sphere(): void
    {
        $a = GeoPoint::make(23.8069, 90.3654);
        $b = GeoPoint::make(23.8069, 90.3600);

        // MySQL returned 549 m for the same pair.
        $this->assertEqualsWithDelta(549, $a->distanceTo($b), 1.0);
    }

    public function test_it_rejects_impossible_coordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeoPoint::make(123, 90);
    }
}
