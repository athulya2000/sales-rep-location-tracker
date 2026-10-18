<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\GeoPoint;
use App\Services\Tracking\TrackSimplifier;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class TrackSimplifierTest extends TestCase
{
    private TrackSimplifier $simplifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simplifier = new TrackSimplifier(tolerance: 0.0001);
    }

    public function test_simplifier_keeps_endpoints_and_reduces_collinear_points(): void
    {
        $startTime = CarbonImmutable::parse('2026-10-18 10:00:00');

        // 10 collinear points along a straight line
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $points[] = new GeoPoint(
                lat: 11.050 + ($i * 0.001),
                lng: 76.070 + ($i * 0.001),
                recordedAt: $startTime->addMinutes($i),
            );
        }

        $simplified = $this->simplifier->simplify($points, 0.0001);

        // All intermediate points are redundant and removed; exactly 2 endpoints remain
        $this->assertCount(2, $simplified);
        $this->assertEquals(11.050, $simplified[0]['lat']);
        $this->assertEquals(76.070, $simplified[0]['lng']);
        $this->assertEquals(11.059, $simplified[1]['lat']);
        $this->assertEquals(76.079, $simplified[1]['lng']);
    }

    public function test_simplifier_preserves_significant_sharp_turns(): void
    {
        $startTime = CarbonImmutable::parse('2026-10-18 10:00:00');

        // Right angle turn: (0,0) -> (0, 0.05) -> (0.05, 0.05)
        $points = [
            new GeoPoint(lat: 11.000, lng: 76.000, recordedAt: $startTime),
            new GeoPoint(lat: 11.000, lng: 76.050, recordedAt: $startTime->addMinutes(10)),
            new GeoPoint(lat: 11.050, lng: 76.050, recordedAt: $startTime->addMinutes(20)),
        ];

        $simplified = $this->simplifier->simplify($points, 0.0001);

        // Sharp corner must be preserved
        $this->assertCount(3, $simplified);
    }

    public function test_simplifier_handles_short_arrays(): void
    {
        $this->assertSame([], $this->simplifier->simplify([]));

        $p = new GeoPoint(lat: 11.0, lng: 76.0, recordedAt: CarbonImmutable::now());
        $res = $this->simplifier->simplify([$p]);
        $this->assertCount(1, $res);
    }
}
