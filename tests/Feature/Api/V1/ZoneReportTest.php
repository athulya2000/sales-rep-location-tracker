<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\LocationPoint;
use App\Models\Rep;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ZoneReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_report_validates_required_parameters(): void
    {
        // 1. Missing parameters
        $response = $this->getJson(route('api.v1.zones.reps'));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'coordinates']);

        // 2. Fewer than 3 polygon coordinates
        $response = $this->getJson(route('api.v1.zones.reps', [
            'date' => '2026-10-18',
            'coordinates' => json_encode([[11.05, 76.07], [11.06, 76.07]]),
        ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coordinates']);
    }

    public function test_zone_report_counts_reps_inside_and_excludes_reps_outside(): void
    {
        $date = '2026-10-18';
        $repInside = Rep::factory()->create(['name' => 'Inside Rep']);
        $repOutside = Rep::factory()->create(['name' => 'Outside Rep']);

        $polygon = [
            [11.050, 76.070],
            [11.060, 76.070],
            [11.060, 76.080],
            [11.050, 76.080],
        ];

        $time = CarbonImmutable::parse("{$date} 10:00:00");

        // Points inside zone for 10 minutes (20 points at 30s interval = 19 * 30s = 570s)
        $insidePoints = [];
        for ($i = 0; $i < 20; $i++) {
            $t = $time->addSeconds($i * 30);
            $insidePoints[] = [
                'rep_id' => $repInside->id,
                'lat' => 11.055,
                'lng' => 76.075,
                'recorded_at' => $t->toDateTimeString(),
                'recorded_date' => $date,
                'accuracy' => 4,
            ];
        }
        LocationPoint::query()->insert($insidePoints);

        // Points outside zone
        $outsidePoints = [];
        for ($i = 0; $i < 20; $i++) {
            $t = $time->addSeconds($i * 30);
            $outsidePoints[] = [
                'rep_id' => $repOutside->id,
                'lat' => 11.040,
                'lng' => 76.060,
                'recorded_at' => $t->toDateTimeString(),
                'recorded_date' => $date,
                'accuracy' => 4,
            ];
        }
        LocationPoint::query()->insert($outsidePoints);

        $response = $this->getJson(route('api.v1.zones.reps', [
            'date' => $date,
            'coordinates' => json_encode($polygon),
        ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rep_id', $repInside->id)
            ->assertJsonPath('data.0.name', 'Inside Rep')
            ->assertJsonPath('data.0.seconds_in_zone', 570)
            ->assertJsonPath('data.0.minutes_in_zone', 9.5);
    }
}
