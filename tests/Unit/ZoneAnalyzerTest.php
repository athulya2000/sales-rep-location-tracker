<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\GeoPoint;
use App\Services\Tracking\ZoneAnalyzer;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class ZoneAnalyzerTest extends TestCase
{
    private array $polygon;

    protected function setUp(): void
    {
        parent::setUp();
        // Polygon around (11.050, 76.070) to (11.060, 76.080)
        $this->polygon = [
            [11.050, 76.070],
            [11.060, 76.070],
            [11.060, 76.080],
            [11.050, 76.080],
        ];
    }

    public function test_rep_inside_polygon_time_is_counted(): void
    {
        $analyzer = new ZoneAnalyzer(gapCapSeconds: 300);
        $startTime = CarbonImmutable::parse('2026-10-18 10:00:00');

        $points = [];
        // 10 points 60 seconds apart inside the zone (11.055, 76.075) -> 9 intervals of 60s = 540s
        for ($i = 0; $i < 10; $i++) {
            $points[] = new GeoPoint(
                lat: 11.055,
                lng: 76.075,
                recordedAt: $startTime->addSeconds($i * 60),
            );
        }

        $seconds = $analyzer->calculateTimeInZone($points, $this->polygon);

        $this->assertEquals(540, $seconds);
    }

    public function test_rep_outside_polygon_returns_zero_time(): void
    {
        $analyzer = new ZoneAnalyzer(gapCapSeconds: 300);
        $startTime = CarbonImmutable::parse('2026-10-18 10:00:00');

        $points = [];
        // Points outside (11.040, 76.060)
        for ($i = 0; $i < 10; $i++) {
            $points[] = new GeoPoint(
                lat: 11.040,
                lng: 76.060,
                recordedAt: $startTime->addSeconds($i * 60),
            );
        }

        $seconds = $analyzer->calculateTimeInZone($points, $this->polygon);

        $this->assertEquals(0, $seconds);
    }

    public function test_gap_larger_than_cap_is_clamped_to_gap_cap(): void
    {
        // Set gap cap to 300 seconds (5 minutes)
        $analyzer = new ZoneAnalyzer(gapCapSeconds: 300);
        $startTime = CarbonImmutable::parse('2026-10-18 10:00:00');

        // Point 1 inside at 10:00:00
        // Point 2 inside at 12:00:00 (2 hours = 7200s gap due to device outage)
        $points = [
            new GeoPoint(lat: 11.055, lng: 76.075, recordedAt: $startTime),
            new GeoPoint(lat: 11.055, lng: 76.075, recordedAt: $startTime->addHours(2)),
        ];

        $seconds = $analyzer->calculateTimeInZone($points, $this->polygon);

        // Must be capped at 300s, not 7200s
        $this->assertEquals(300, $seconds);
    }
}
