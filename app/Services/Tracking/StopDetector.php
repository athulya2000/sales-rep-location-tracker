<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\DTOs\GeoPoint;
use App\Models\LocationPoint;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Config;

final class StopDetector
{
    public function __construct(
        private readonly ?float $radiusMeters = null,
        private readonly ?int $minStopDurationSeconds = null,
        private readonly ?int $maxGapSeconds = null,
    ) {}

    /**
     * Detect stationary stops from an iterable sequence of location points.
     * Uses the distance-from-anchor clustering algorithm.
     *
     * @param  iterable<int, GeoPoint|LocationPoint|array{lat: float|int|string, lng: float|int|string, recorded_at: string|DateTimeInterface, accuracy?: int|null}>  $points
     * @return list<array{date: string, lat: float, lng: float, started_at: string, ended_at: string, duration_seconds: int}>
     */
    public function detect(iterable $points): array
    {
        $radius = $this->radiusMeters ?? (float) Config::get('tracking.radius_meters', 50.0);
        $minDuration = $this->minStopDurationSeconds ?? (int) Config::get('tracking.min_stop_duration_seconds', 900);
        $maxGap = $this->maxGapSeconds ?? (int) Config::get('tracking.max_gap_seconds', 1800);

        $stops = [];

        /** @var GeoPoint|null $anchor */
        $anchor = null;
        /** @var GeoPoint|null $lastPoint */
        $lastPoint = null;
        /** @var list<GeoPoint> $clusterPoints */
        $clusterPoints = [];

        foreach ($points as $rawPoint) {
            $point = $this->normalizePoint($rawPoint);

            if ($anchor === null) {
                $anchor = $point;
                $lastPoint = $point;
                $clusterPoints = [$point];

                continue;
            }

            assert($lastPoint !== null);
            $timeGap = $point->recordedAt->getTimestamp() - $lastPoint->recordedAt->getTimestamp();

            // Check if there is an excessive time gap between consecutive points (signal loss / power off)
            if ($timeGap > $maxGap) {
                $this->evaluateAndEmitStop($clusterPoints, $minDuration, $stops);
                $anchor = $point;
                $lastPoint = $point;
                $clusterPoints = [$point];

                continue;
            }

            // Calculate distance from current cluster anchor
            $distanceFromAnchor = Geo::haversine($anchor->lat, $anchor->lng, $point->lat, $point->lng);

            if ($distanceFromAnchor <= $radius) {
                // Point extends the stationary cluster
                $clusterPoints[] = $point;
                $lastPoint = $point;
            } else {
                // Representative has moved outside the anchor radius; close and evaluate current cluster
                $this->evaluateAndEmitStop($clusterPoints, $minDuration, $stops);
                $anchor = $point;
                $lastPoint = $point;
                $clusterPoints = [$point];
            }
        }

        // Evaluate the final cluster after all points are processed
        if (! empty($clusterPoints)) {
            $this->evaluateAndEmitStop($clusterPoints, $minDuration, $stops);
        }

        return $stops;
    }

    /**
     * @param  list<GeoPoint>  $clusterPoints
     * @param  list<array{date: string, lat: float, lng: float, started_at: string, ended_at: string, duration_seconds: int}>  $stops
     */
    private function evaluateAndEmitStop(array $clusterPoints, int $minDuration, array &$stops): void
    {
        $count = count($clusterPoints);
        if ($count < 2) {
            return;
        }

        $first = $clusterPoints[0];
        $last = $clusterPoints[$count - 1];

        $durationSeconds = $last->recordedAt->getTimestamp() - $first->recordedAt->getTimestamp();

        if ($durationSeconds >= $minDuration) {
            $sumLat = 0.0;
            $sumLng = 0.0;

            foreach ($clusterPoints as $p) {
                $sumLat += $p->lat;
                $sumLng += $p->lng;
            }

            $stops[] = [
                'date' => $first->recordedAt->toDateString(),
                'lat' => round($sumLat / $count, 6),
                'lng' => round($sumLng / $count, 6),
                'started_at' => $first->recordedAt->toDateTimeString(),
                'ended_at' => $last->recordedAt->toDateTimeString(),
                'duration_seconds' => $durationSeconds,
            ];
        }
    }

    /**
     * @param  GeoPoint|LocationPoint|array{lat: float|int|string, lng: float|int|string, recorded_at: string|DateTimeInterface, accuracy?: int|null}  $point
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

        throw new \InvalidArgumentException('Unsupported point type provided to StopDetector.');
    }
}
