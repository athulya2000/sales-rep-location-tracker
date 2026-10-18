<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\GeoPoint;
use App\Services\Tracking\StopDetector;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class StopDetectorTest extends TestCase
{
    private StopDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        // 50m radius, 900s (15 min) duration, 1800s max gap
        $this->detector = new StopDetector(radiusMeters: 50.0, minStopDurationSeconds: 900, maxGapSeconds: 1800);
    }

    public function test_empty_and_single_point_inputs_return_empty_array(): void
    {
        $this->assertSame([], $this->detector->detect([]));

        $singlePoint = [
            new GeoPoint(lat: 11.050, lng: 76.070, recordedAt: CarbonImmutable::parse('2026-10-18 10:00:00')),
        ];

        $this->assertSame([], $this->detector->detect($singlePoint));
    }

    public function test_twenty_minutes_within_thirty_meters_detects_stop(): void
    {
        $startTime = CarbonImmutable::parse('2026-10-18 10:00:00');
        $points = [];

        // 20 minutes (80 points at 15s interval) jittering within ~20-30 meters
        for ($i = 0; $i <= 80; $i++) {
            $points[] = new GeoPoint(
                lat: 11.050000 + ($i % 3) * 0.00010, // ~11 to 22 meters
                lng: 76.070000 + ($i % 2) * 0.00010,
                recordedAt: $startTime->addSeconds($i * 15),
            );
        }

        $stops = $this->detector->detect($points);

        $this->assertCount(1, $stops);
        $this->assertEquals(1200, $stops[0]['duration_seconds']);
        $this->assertEquals('2026-10-18 10:00:00', $stops[0]['started_at']);
        $this->assertEquals('2026-10-18 10:20:00', $stops[0]['ended_at']);
        $this->assertEquals('2026-10-18', $stops[0]['date']);
    }

    public function test_ten_minutes_stationary_does_not_count_as_stop(): void
    {
        $startTime = CarbonImmutable::parse('2026-10-18 10:00:00');
        $points = [];

        // 10 minutes (40 points at 15s interval = 600s < 900s threshold)
        for ($i = 0; $i <= 40; $i++) {
            $points[] = new GeoPoint(
                lat: 11.050000,
                lng: 76.070000,
                recordedAt: $startTime->addSeconds($i * 15),
            );
        }

        $stops = $this->detector->detect($points);

        $this->assertCount(0, $stops);
    }

    public function test_moving_more_than_fifty_meters_does_not_count_as_stop(): void
    {
        $startTime = CarbonImmutable::parse('2026-10-18 10:00:00');
        $points = [];

        // Moving 60 meters every minute (approx 0.0006 deg) for 30 minutes
        for ($i = 0; $i <= 30; $i++) {
            $points[] = new GeoPoint(
                lat: 11.050000 + ($i * 0.00060),
                lng: 76.070000 + ($i * 0.00060),
                recordedAt: $startTime->addMinutes($i),
            );
        }

        $stops = $this->detector->detect($points);

        $this->assertCount(0, $stops);
    }
}
