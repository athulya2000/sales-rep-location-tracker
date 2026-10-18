<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Jobs\ComputeStopsJob;
use App\Models\Rep;
use App\Repositories\LocationPointRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class DemoDaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(LocationPointRepository $repository): void
    {
        $this->call(RepSeeder::class);

        $reps = Rep::query()->orderBy('id')->get();
        if ($reps->count() < 3) {
            return;
        }

        $date = CarbonImmutable::now('UTC')->toDateString();

        $rep1 = $reps[0]; // Rahul Nair
        $rep2 = $reps[1]; // Aisha Rahman
        $rep3 = $reps[2]; // Mohammed Shafi

        // Demo Zone: Malappuram Town Commercial Hub (~[11.0500, 76.0680] to [11.0580, 76.0780])

        // 1. Rep 1: Traverses through Malappuram, spends ~45 mins in the commercial zone with a 25m client stop
        $points1 = $this->generateRep1Points($date);
        $repository->insertBatch($rep1->id, $points1);
        ComputeStopsJob::dispatchSync($rep1->id, $date);

        // 2. Rep 2: Crosses the commercial zone briefly (~18 minutes), then moves to industrial zone
        $points2 = $this->generateRep2Points($date);
        $repository->insertBatch($rep2->id, $points2);
        ComputeStopsJob::dispatchSync($rep2->id, $date);

        // 3. Rep 3: Operates completely outside the commercial zone (East Malappuram, ~76.0900)
        $points3 = $this->generateRep3Points($date);
        $repository->insertBatch($rep3->id, $points3);
        ComputeStopsJob::dispatchSync($rep3->id, $date);
    }

    /**
     * @return list<array{lat: float, lng: float, recorded_at: string, accuracy: int}>
     */
    private function generateRep1Points(string $date): array
    {
        $points = [];
        $time = CarbonImmutable::parse("{$date} 09:00:00", 'UTC');

        // Approach zone (outside: 11.040, 76.060)
        $lat = 11.042000;
        $lng = 76.062000;
        for ($i = 0; $i < 60; $i++) {
            $lat += 0.00015;
            $lng += 0.00012;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 5,
            ];
            $time = $time->addSeconds(15);
        }

        // Inside Zone: walks inside the zone for 15 minutes (11.052, 76.071)
        for ($i = 0; $i < 60; $i++) {
            $lat += 0.00003;
            $lng += 0.00004;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 4,
            ];
            $time = $time->addSeconds(15);
        }

        // Inside Zone: Stationary client stop for 25 minutes (11.054, 76.073)
        $stopBaseLat = $lat;
        $stopBaseLng = $lng;
        for ($i = 0; $i < 100; $i++) {
            $points[] = [
                'lat' => round($stopBaseLat + (mt_rand(-5, 5) / 1000000), 6),
                'lng' => round($stopBaseLng + (mt_rand(-5, 5) / 1000000), 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 4,
            ];
            $time = $time->addSeconds(15);
        }

        // Inside Zone: walks for another 5 minutes before leaving zone
        for ($i = 0; $i < 20; $i++) {
            $lat += 0.00010;
            $lng += 0.00012;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 5,
            ];
            $time = $time->addSeconds(15);
        }

        // Exits zone to the north (lat > 11.059) and continues walking for 40 mins
        for ($i = 0; $i < 160; $i++) {
            $lat += 0.00008;
            $lng += 0.00006;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 6,
            ];
            $time = $time->addSeconds(15);
        }

        return $points;
    }

    /**
     * @return list<array{lat: float, lng: float, recorded_at: string, accuracy: int}>
     */
    private function generateRep2Points(string $date): array
    {
        $points = [];
        $time = CarbonImmutable::parse("{$date} 09:30:00", 'UTC');

        // Approach from West
        $lat = 11.053000;
        $lng = 76.061000;
        for ($i = 0; $i < 40; $i++) {
            $lng += 0.00020;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 5,
            ];
            $time = $time->addSeconds(15);
        }

        // Inside Zone for ~18 mins (72 steps * 15s)
        for ($i = 0; $i < 72; $i++) {
            $lat += 0.00004;
            $lng += 0.00008;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 4,
            ];
            $time = $time->addSeconds(15);
        }

        // Exits to South-East
        for ($i = 0; $i < 60; $i++) {
            $lat -= 0.00010;
            $lng += 0.00015;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 6,
            ];
            $time = $time->addSeconds(15);
        }

        return $points;
    }

    /**
     * @return list<array{lat: float, lng: float, recorded_at: string, accuracy: int}>
     */
    private function generateRep3Points(string $date): array
    {
        $points = [];
        $time = CarbonImmutable::parse("{$date} 08:45:00", 'UTC');

        // Outside to East: ~11.045 to 11.055, lng ~76.095 to 76.105 (completely outside zone)
        $lat = 11.045000;
        $lng = 76.095000;
        for ($i = 0; $i < 150; $i++) {
            $lat += 0.00007;
            $lng += 0.00005;
            $points[] = [
                'lat' => round($lat, 6),
                'lng' => round($lng, 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 5,
            ];
            $time = $time->addSeconds(15);
        }

        // Stop outside the zone for 30 minutes
        $baseLat = $lat;
        $baseLng = $lng;
        for ($i = 0; $i < 120; $i++) {
            $points[] = [
                'lat' => round($baseLat + (mt_rand(-5, 5) / 1000000), 6),
                'lng' => round($baseLng + (mt_rand(-5, 5) / 1000000), 6),
                'recorded_at' => $time->toIso8601String(),
                'accuracy' => 3,
            ];
            $time = $time->addSeconds(15);
        }

        return $points;
    }
}
