<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\LocationPoint;
use App\Models\Rep;
use App\Models\Stop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StopReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_stop_report_returns_404_for_unknown_rep(): void
    {
        $response = $this->getJson('/api/v1/reps/9999/stops?date=2026-10-18');
        $response->assertStatus(404);
    }

    public function test_stop_report_validates_date_format(): void
    {
        $rep = Rep::factory()->create();

        // Missing date
        $response = $this->getJson(route('api.v1.reps.stops', ['rep' => $rep]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);

        // Invalid date format
        $response = $this->getJson(route('api.v1.reps.stops', ['rep' => $rep, 'date' => 'invalid-date']));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_stop_report_retrieves_precomputed_stops(): void
    {
        $rep = Rep::factory()->create();
        $date = '2026-10-18';

        Stop::query()->create([
            'rep_id' => $rep->id,
            'date' => $date,
            'lat' => 11.051234,
            'lng' => 76.071234,
            'started_at' => '2026-10-18 10:00:00',
            'ended_at' => '2026-10-18 10:25:00',
            'duration_seconds' => 1500,
        ]);

        $response = $this->getJson(route('api.v1.reps.stops', ['rep' => $rep, 'date' => $date]));

        $response->assertStatus(200)
            ->assertJsonPath('rep_id', $rep->id)
            ->assertJsonPath('date', $date)
            ->assertJsonCount(1, 'stops')
            ->assertJsonPath('stops.0.lat', 11.051234);

        $this->assertEquals(25.0, $response->json('stops.0.duration_minutes'));
    }

    public function test_stop_report_computes_stops_on_demand_when_not_yet_precomputed(): void
    {
        $rep = Rep::factory()->create();
        $date = '2026-10-18';
        $startTime = CarbonImmutable::parse("{$date} 10:00:00");

        // Create 20 minutes of stationary points (80 points at 15s interval)
        $points = [];
        for ($i = 0; $i <= 80; $i++) {
            $time = $startTime->addSeconds($i * 15);
            $points[] = [
                'rep_id' => $rep->id,
                'lat' => 11.050000,
                'lng' => 76.070000,
                'recorded_at' => $time->toDateTimeString(),
                'recorded_date' => $date,
                'accuracy' => 5,
            ];
        }
        LocationPoint::query()->insert($points);

        $response = $this->getJson(route('api.v1.reps.stops', ['rep' => $rep, 'date' => $date]));

        $response->assertStatus(200)
            ->assertJsonPath('rep_id', $rep->id)
            ->assertJsonPath('date', $date)
            ->assertJsonCount(1, 'stops');

        $this->assertEquals(20.0, $response->json('stops.0.duration_minutes'));
    }
}
