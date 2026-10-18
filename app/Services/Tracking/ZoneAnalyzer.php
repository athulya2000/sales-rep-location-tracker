<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\DTOs\GeoPoint;
use App\Models\LocationPoint;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Config;

final class ZoneAnalyzer
{
    public function __construct(
        private readonly ?int $gapCapSeconds = null,
    ) {}

    /**
     * Calculate total time spent inside a polygon zone in seconds for an ordered sequence of points.
     *
     * @param  iterable<int, GeoPoint|LocationPoint|array{lat: float|int|string, lng: float|int|string, recorded_at: string|DateTimeInterface}>  $points
     * @param  list<array{0: float|int|string, 1: float|int|string}|list<float>>  $polygon
     */
    public function calculateTimeInZone(iterable $points, array $polygon): int
    {
        $gapCap = $this->gapCapSeconds ?? (int) Config::get('tracking.zone_gap_cap_seconds', 300);

        $totalSeconds = 0;
        /** @var GeoPoint|null $prevPoint */
        $prevPoint = null;
        /** @var bool|null $prevInside */
        $prevInside = null;

        foreach ($points as $rawPoint) {
            $currentPoint = $this->normalizePoint($rawPoint);
            $currentInside = Geo::pointInPolygon($currentPoint->lat, $currentPoint->lng, $polygon);

            if ($prevPoint !== null && $prevInside === true) {
                $diff = $currentPoint->recordedAt->getTimestamp() - $prevPoint->recordedAt->getTimestamp();
                if ($diff > 0) {
                    $duration = min($diff, $gapCap);
                    $totalSeconds += $duration;
                }
            }

            $prevPoint = $currentPoint;
            $prevInside = $currentInside;
        }

        return $totalSeconds;
    }

    /**
     * @param  GeoPoint|LocationPoint|array{lat: float|int|string, lng: float|int|string, recorded_at: string|DateTimeInterface}  $point
     */
    private function normalizePoint(mixed $point): GeoPoint
    {
        if ($point instanceof GeoPoint) {
            return $point;
        }

        if ($point instanceof LocationPoint) {
            $recordedAt = $point->recorded_at instanceof CarbonImmutable
                ? $point->recorded_at
                : CarbonImmutable::instance($point->recorded_at);

            return new GeoPoint(
                lat: (float) $point->lat,
                lng: (float) $point->lng,
                recordedAt: $recordedAt,
                accuracy: $point->accuracy !== null ? (int) $point->accuracy : null,
            );
        }

        if (is_array($point)) {
            return GeoPoint::fromArray($point);
        }

        throw new \InvalidArgumentException('Unsupported point type provided to ZoneAnalyzer.');
    }
}
