<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\LocationPoint;
use App\Models\Stop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final class LocationPointRepository
{
    /**
     * Bulk insert location points with idempotency (insertOrIgnore).
     *
     * @param  list<array{lat: float|int|string, lng: float|int|string, recorded_at: string, accuracy?: int|null}>  $points
     * @return array{accepted: int, inserted: int, duplicates: int, affected_dates: list<string>}
     */
    public function insertBatch(int $repId, array $points): array
    {
        $total = count($points);
        if ($total === 0) {
            return [
                'accepted' => 0,
                'inserted' => 0,
                'duplicates' => 0,
                'affected_dates' => [],
            ];
        }

        $chunkSize = (int) Config::get('tracking.batch_chunk_size', 500);
        $insertedCount = 0;
        $affectedDates = [];

        $preparedRows = [];
        foreach ($points as $point) {
            $recordedAt = CarbonImmutable::parse((string) $point['recorded_at'])->utc();
            $recordedDate = $recordedAt->toDateString();
            $affectedDates[$recordedDate] = true;

            $preparedRows[] = [
                'rep_id' => $repId,
                'recorded_at' => $recordedAt->toDateTimeString(),
                'recorded_date' => $recordedDate,
                'lat' => round((float) $point['lat'], 6),
                'lng' => round((float) $point['lng'], 6),
                'accuracy' => isset($point['accuracy']) ? (int) $point['accuracy'] : null,
            ];
        }

        foreach (array_chunk($preparedRows, $chunkSize) as $chunk) {
            $inserted = DB::table('location_points')->insertOrIgnore($chunk);
            $insertedCount += $inserted;
        }

        $duplicates = max(0, $total - $insertedCount);

        return [
            'accepted' => $total,
            'inserted' => $insertedCount,
            'duplicates' => $duplicates,
            'affected_dates' => array_keys($affectedDates),
        ];
    }

    /**
     * Lazily stream location points for a rep on a specific date, ordered by recorded_at.
     *
     * @return LazyCollection<int, LocationPoint>
     */
    public function getPointsLazyForRepAndDate(int $repId, string $date): LazyCollection
    {
        return LocationPoint::query()
            ->where('rep_id', $repId)
            ->where('recorded_date', $date)
            ->orderBy('recorded_at')
            ->lazy();
    }

    /**
     * Get all points for a rep on a specific date ordered by recorded_at.
     *
     * @return Collection<int, LocationPoint>
     */
    public function getPointsForRepAndDate(int $repId, string $date): Collection
    {
        return LocationPoint::query()
            ->where('rep_id', $repId)
            ->where('recorded_date', $date)
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Pre-filter distinct rep IDs that had points within a bounding box on a specific date.
     * Uses the (recorded_date, lat, lng) compound index.
     *
     * @return list<int>
     */
    public function findCandidateRepIdsInBoundingBox(string $date, float $minLat, float $maxLat, float $minLng, float $maxLng): array
    {
        /** @var list<int> $ids */
        $ids = DB::table('location_points')
            ->where('recorded_date', $date)
            ->whereBetween('lat', [$minLat, $maxLat])
            ->whereBetween('lng', [$minLng, $maxLng])
            ->distinct()
            ->pluck('rep_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * Check if stops have already been computed for a rep on a given date.
     */
    public function hasPrecomputedStops(int $repId, string $date): bool
    {
        return Stop::query()
            ->where('rep_id', $repId)
            ->whereDate('date', $date)
            ->exists();
    }

    /**
     * Get computed stops for a rep on a given date.
     *
     * @return Collection<int, Stop>
     */
    public function getStopsForRepAndDate(int $repId, string $date): Collection
    {
        return Stop::query()
            ->where('rep_id', $repId)
            ->whereDate('date', $date)
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Replace computed stops for a rep on a date atomically.
     *
     * @param  list<array{date: string, lat: float, lng: float, started_at: string, ended_at: string, duration_seconds: int}>  $stops
     */
    public function replaceStopsForDate(int $repId, string $date, array $stops): void
    {
        DB::transaction(function () use ($repId, $date, $stops): void {
            Stop::query()
                ->where('rep_id', $repId)
                ->whereDate('date', $date)
                ->delete();

            if (empty($stops)) {
                return;
            }

            $rows = array_map(fn (array $stop): array => [
                'rep_id' => $repId,
                'date' => $stop['date'],
                'lat' => $stop['lat'],
                'lng' => $stop['lng'],
                'started_at' => $stop['started_at'],
                'ended_at' => $stop['ended_at'],
                'duration_seconds' => $stop['duration_seconds'],
            ], $stops);

            DB::table('stops')->insert($rows);
        });
    }
}
