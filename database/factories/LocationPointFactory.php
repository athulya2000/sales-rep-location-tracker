<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LocationPoint;
use App\Models\Rep;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationPoint>
 */
final class LocationPointFactory extends Factory
{
    /**
     * @var class-string<LocationPoint>
     */
    protected $model = LocationPoint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recordedAt = CarbonImmutable::instance(fake()->dateTimeBetween('-7 days', 'now'));

        return [
            'rep_id' => Rep::factory(),
            'recorded_at' => $recordedAt,
            'recorded_date' => $recordedAt->toDateString(),
            'lat' => fake()->latitude(11.0, 11.1),
            'lng' => fake()->longitude(76.0, 76.1),
            'accuracy' => fake()->numberBetween(3, 15),
        ];
    }
}
