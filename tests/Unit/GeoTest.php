<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

final class GeoTest extends TestCase
{
    public function test_haversine_calculates_distance_between_known_coordinates(): void
    {
        // Distance from London to Paris is approx 343.5 km
        $londonLat = 51.5074;
        $londonLng = -0.1278;
        $parisLat = 48.8566;
        $parisLng = 2.3522;

        $distance = Geo::haversine($londonLat, $londonLng, $parisLat, $parisLng);

        $this->assertEqualsWithDelta(343500.0, $distance, 1500.0);

        // Distance to identical coordinates is 0
        $zeroDistance = Geo::haversine(11.0500, 76.0700, 11.0500, 76.0700);
        $this->assertEquals(0.0, $zeroDistance);
    }

    public function test_point_in_polygon_identifies_inside_outside_and_edge(): void
    {
        // Square polygon around (11.05, 76.07)
        $polygon = [
            [11.050, 76.070],
            [11.060, 76.070],
            [11.060, 76.080],
            [11.050, 76.080],
        ];

        // Inside point
        $this->assertTrue(Geo::pointInPolygon(11.055, 76.075, $polygon));

        // Outside point
        $this->assertFalse(Geo::pointInPolygon(11.040, 76.060, $polygon));
        $this->assertFalse(Geo::pointInPolygon(11.070, 76.090, $polygon));

        // Vertex
        $this->assertTrue(Geo::pointInPolygon(11.050, 76.070, $polygon));

        // Less than 3 vertices
        $this->assertFalse(Geo::pointInPolygon(11.055, 76.075, [[11.050, 76.070], [11.060, 76.070]]));
    }

    public function test_bounding_box_computes_envelope(): void
    {
        $polygon = [
            [11.050, 76.070],
            [11.065, 76.065],
            [11.058, 76.082],
        ];

        $bbox = Geo::boundingBox($polygon);

        $this->assertEquals(11.050, $bbox['min_lat']);
        $this->assertEquals(11.065, $bbox['max_lat']);
        $this->assertEquals(76.065, $bbox['min_lng']);
        $this->assertEquals(76.082, $bbox['max_lng']);
    }
}
