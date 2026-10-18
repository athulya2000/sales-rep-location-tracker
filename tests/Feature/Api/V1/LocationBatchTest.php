<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\ComputeStopsJob;
use App\Models\Rep;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class LocationBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_ingestion_validates_payload_and_returns_422(): void
    {
        // 1. Missing rep_id and points
        $response = $this->postJson(route('api.v1.locations.batch'), []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rep_id', 'points']);

        // 2. Non-existent rep_id
        $response = $this->postJson(route('api.v1.locations.batch'), [
            'rep_id' => 9999,
            'points' => [
                ['lat' => 11.05, 'lng' => 76.07, 'recorded_at' => '2026-10-18T10:00:00Z'],
            ],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rep_id']);

        // 3. Out-of-bounds coordinates
        $rep = Rep::factory()->create();
        $response = $this->postJson(route('api.v1.locations.batch'), [
            'rep_id' => $rep->id,
            'points' => [
                ['lat' => 120.0, 'lng' => 200.0, 'recorded_at' => '2026-10-18T10:00:00Z'],
            ],
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['points.0.lat', 'points.0.lng']);
    }

    public function test_batch_ingestion_is_idempotent_and_returns_202(): void
    {
        Queue::fake();

        $rep = Rep::factory()->create();
        $now = CarbonImmutable::now('UTC');

        $payload = [
            'rep_id' => $rep->id,
            'points' => [
                [
                    'lat' => 11.051234,
                    'lng' => 76.071234,
                    'recorded_at' => $now->subMinutes(5)->toIso8601String(),
                    'accuracy' => 5,
                ],
                [
                    'lat' => 11.051280,
                    'lng' => 76.071290,
                    'recorded_at' => $now->subMinutes(4)->toIso8601String(),
                    'accuracy' => 6,
                ],
            ],
        ];

        // First ingestion: 2 accepted, 2 inserted, 0 duplicates
        $response = $this->postJson(route('api.v1.locations.batch'), $payload);
        $response->assertStatus(202)
            ->assertJson([
                'accepted' => 2,
                'inserted' => 2,
                'duplicates' => 0,
            ]);

        $this->assertDatabaseCount('location_points', 2);
        Queue::assertPushed(ComputeStopsJob::class);

        // Second ingestion with identical points: 2 accepted, 0 inserted, 2 duplicates (idempotent)
        $secondResponse = $this->postJson(route('api.v1.locations.batch'), $payload);
        $secondResponse->assertStatus(202)
            ->assertJson([
                'accepted' => 2,
                'inserted' => 0,
                'duplicates' => 2,
            ]);

        $this->assertDatabaseCount('location_points', 2);
    }
}
