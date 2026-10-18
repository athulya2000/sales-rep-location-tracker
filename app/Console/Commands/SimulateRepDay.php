<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ComputeStopsJob;
use App\Models\Rep;
use App\Repositories\LocationPointRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class SimulateRepDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracking:simulate
                            {rep : The ID or name of the sales representative}
                            {date? : The date to simulate in YYYY-MM-DD format (defaults to today)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate a realistic workday trajectory with walking, stops, short pauses, and signal gaps';

    /**
     * Execute the console command.
     */
    public function handle(LocationPointRepository $repository): int
    {
        $repInput = (string) $this->argument('rep');
        $dateInput = (string) ($this->argument('date') ?? CarbonImmutable::now('UTC')->toDateString());

        /** @var Rep|null $rep */
        $rep = is_numeric($repInput)
            ? Rep::query()->find((int) $repInput)
            : Rep::query()->where('name', $repInput)->first();

        if ($rep === null) {
            $rep = Rep::query()->create([
                'name' => is_numeric($repInput) ? 'Sales Rep #'.$repInput : $repInput,
            ]);
            $this->info("Created new representative: [ID: {$rep->id}] {$rep->name}");
        }

        $this->info("Simulating shift for {$rep->name} on {$dateInput}...");

        $points = $this->generateWorkdayPoints($dateInput);
        $totalPoints = count($points);
        $this->info("Generated {$totalPoints} GPS data points.");

        $result = $repository->insertBatch($rep->id, $points);
        $this->info("Ingested points: {$result['inserted']} inserted, {$result['duplicates']} duplicates (Total accepted: {$result['accepted']}).");

        $this->info('Computing precomputed stops...');
        ComputeStopsJob::dispatchSync($rep->id, $dateInput);

        $stops = $repository->getStopsForRepAndDate($rep->id, $dateInput);
        $this->info("Successfully detected {$stops->count()} stops for the day:");

        foreach ($stops as $idx => $stop) {
            $durationMin = round($stop->duration_seconds / 60, 1);
            $this->line('  [#'.($idx + 1)."] {$stop->started_at->format('H:i:s')} - {$stop->ended_at->format('H:i:s')} ({$durationMin} mins) at [{$stop->lat}, {$stop->lng}]");
        }

        return self::SUCCESS;
    }

    /**
     * Generate synthetic trajectory for a realistic sales rep day.
     *
     * @return list<array{lat: float, lng: float, recorded_at: string, accuracy: int}>
     */
    private function generateWorkdayPoints(string $date): array
    {
        $startTime = CarbonImmutable::parse("{$date} 08:30:00", 'UTC');
        $currentTime = $startTime;

        // Base location: Malappuram, Kerala (~11.0500, 76.0700)
        $currentLat = 11.051000;
        $currentLng = 76.071000;

        $points = [];

        // 1. Walking leg 1 (08:30 - 09:30): 60 minutes, walking ~1.4 m/s
        $this->addWalkingLeg($points, $currentTime, $currentLat, $currentLng, 60 * 60, 0.00012, 0.00008);

        // 2. Client Stop 1 (09:30 - 09:55): 25 minutes stationary stop (stationary < 50m)
        $this->addStationaryStop($points, $currentTime, $currentLat, $currentLng, 25 * 60);

        // 3. Walking leg 2 (09:55 - 11:00): 65 minutes
        $this->addWalkingLeg($points, $currentTime, $currentLat, $currentLng, 65 * 60, 0.00009, 0.00014);

        // 4. Short pause / Coffee (11:00 - 11:10): 10 minutes stationary (MUST NOT count as a stop)
        $this->addStationaryStop($points, $currentTime, $currentLat, $currentLng, 10 * 60);

        // 5. Walking leg 3 (11:10 - 12:30): 80 minutes
        $this->addWalkingLeg($points, $currentTime, $currentLat, $currentLng, 80 * 60, -0.00011, 0.00005);

        // 6. Lunch break (12:30 - 13:05): 35 minutes stationary stop (MUST count as a stop)
        $this->addStationaryStop($points, $currentTime, $currentLat, $currentLng, 35 * 60);

        // 7. Walking leg 4 (13:05 - 14:15): 70 minutes
        $this->addWalkingLeg($points, $currentTime, $currentLat, $currentLng, 70 * 60, -0.00008, -0.00012);

        // 8. Tunnel / Underground / Signal Gap (14:15 - 14:40): 25 minutes NO points emitted
        $currentTime = $currentTime->addMinutes(25);

        // 9. Walking leg 5 (14:40 - 15:30): 50 minutes
        $this->addWalkingLeg($points, $currentTime, $currentLat, $currentLng, 50 * 60, 0.00006, -0.00010);

        // 10. Afternoon client visit (15:30 - 15:52): 22 minutes stationary stop (MUST count as a stop)
        $this->addStationaryStop($points, $currentTime, $currentLat, $currentLng, 22 * 60);

        // 11. Final walking return leg (15:52 - 17:00): 68 minutes
        $this->addWalkingLeg($points, $currentTime, $currentLat, $currentLng, 68 * 60, 0.00005, 0.00007);

        return $points;
    }

    /**
     * @param  list<array{lat: float, lng: float, recorded_at: string, accuracy: int}>  $points
     */
    private function addWalkingLeg(
        array &$points,
        CarbonImmutable &$currentTime,
        float &$currentLat,
        float &$currentLng,
        int $durationSeconds,
        float $dLatPerSec,
        float $dLngPerSec,
    ): void {
        $interval = 15; // Point every 15 seconds
        $steps = intdiv($durationSeconds, $interval);

        for ($i = 0; $i < $steps; $i++) {
            $currentLat += ($dLatPerSec * $interval) + ((mt_rand(-10, 10)) / 10000000);
            $currentLng += ($dLngPerSec * $interval) + ((mt_rand(-10, 10)) / 10000000);

            $points[] = [
                'lat' => round($currentLat, 6),
                'lng' => round($currentLng, 6),
                'recorded_at' => $currentTime->toIso8601String(),
                'accuracy' => mt_rand(4, 12),
            ];

            $currentTime = $currentTime->addSeconds($interval);
        }
    }

    /**
     * @param  list<array{lat: float, lng: float, recorded_at: string, accuracy: int}>  $points
     */
    private function addStationaryStop(
        array &$points,
        CarbonImmutable &$currentTime,
        float $baseLat,
        float $baseLng,
        int $durationSeconds,
    ): void {
        $interval = 15;
        $steps = intdiv($durationSeconds, $interval);

        for ($i = 0; $i < $steps; $i++) {
            // Jitter within ~10 meters (approx 0.00009 degrees)
            $jitterLat = $baseLat + (mt_rand(-9, 9) / 1000000);
            $jitterLng = $baseLng + (mt_rand(-9, 9) / 1000000);

            $points[] = [
                'lat' => round($jitterLat, 6),
                'lng' => round($jitterLng, 6),
                'recorded_at' => $currentTime->toIso8601String(),
                'accuracy' => mt_rand(3, 8),
            ];

            $currentTime = $currentTime->addSeconds($interval);
        }
    }
}
