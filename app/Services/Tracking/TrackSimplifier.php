<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\DTOs\GeoPoint;
use App\Models\LocationPoint;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Config;

final class TrackSimplifier
{
    public function __construct(
        private readonly ?float $tolerance = null,
    ) {}

    /**
     * Simplify a sequence of GPS coordinates using the Ramer-Douglas-Peucker algorithm.
     *
     * @param  list<GeoPoint|LocationPoint|array{lat: float|int|string, lng: float|int|string, recorded_at?: string|DateTimeInterface}>  $points
     * @return list<array{lat: float, lng: float, recorded_at: string}>
     */
    public function simplify(array $points, ?float $epsilon = null): array
    {
        $count = count($points);
        if ($count <= 2) {
            return array_map(fn ($p) => $this->formatPoint($p), array_values($points));
        }

        $tolerance = $epsilon ?? $this->tolerance ?? (float) Config::get('tracking.rdp_tolerance', 0.00008);

        $normalized = array_map(fn ($p) => $this->formatPoint($p), array_values($points));

        return $this->rdp($normalized, 0, $count - 1, $tolerance);
    }

    /**
     * @param  list<array{lat: float, lng: float, recorded_at: string}>  $points
     * @return list<array{lat: float, lng: float, recorded_at: string}>
     */
    private function rdp(array $points, int $startIndex, int $endIndex, float $epsilon): array
    {
        $dmax = 0.0;
        $index = $startIndex;

        $p1 = $points[$startIndex];
        $p2 = $points[$endIndex];

        for ($i = $startIndex + 1; $i < $endIndex; $i++) {
            $d = $this->perpendicularDistance($points[$i], $p1, $p2);
            if ($d > $dmax) {
                $index = $i;
                $dmax = $d;
            }
        }

        if ($dmax > $epsilon) {
            $recResults1 = $this->rdp($points, $startIndex, $index, $epsilon);
            $recResults2 = $this->rdp($points, $index, $endIndex, $epsilon);

            // Merge sub-results without duplicating the middle point
            array_pop($recResults1);

            return array_merge($recResults1, $recResults2);
        }

        return [$points[$startIndex], $points[$endIndex]];
    }

    /**
     * @param  array{lat: float, lng: float, recorded_at: string}  $point
     * @param  array{lat: float, lng: float, recorded_at: string}  $lineStart
     * @param  array{lat: float, lng: float, recorded_at: string}  $lineEnd
     */
    private function perpendicularDistance(array $point, array $lineStart, array $lineEnd): float
    {
        $x0 = $point['lng'];
        $y0 = $point['lat'];
        $x1 = $lineStart['lng'];
        $y1 = $lineStart['lat'];
        $x2 = $lineEnd['lng'];
        $y2 = $lineEnd['lat'];

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;

        if (abs($dx) < 1e-12 && abs($dy) < 1e-12) {
            return hypot($x0 - $x1, $y0 - $y1);
        }

        $numerator = abs($dy * $x0 - $dx * $y0 + $x2 * $y1 - $y2 * $x1);
        $denominator = sqrt($dy ** 2 + $dx ** 2);

        return $numerator / $denominator;
    }

    /**
     * @param  GeoPoint|LocationPoint|array{lat: float|int|string, lng: float|int|string, recorded_at?: string|DateTimeInterface}  $point
     * @return array{lat: float, lng: float, recorded_at: string}
     */
    private function formatPoint(mixed $point): array
    {
        if ($point instanceof GeoPoint) {
            return [
                'lat' => round($point->lat, 6),
                'lng' => round($point->lng, 6),
                'recorded_at' => $point->recordedAt->toIso8601String(),
            ];
        }

        if ($point instanceof LocationPoint) {
            $recordedAt = $point->recorded_at instanceof CarbonImmutable
                ? $point->recorded_at
                : CarbonImmutable::instance($point->recorded_at);

            return [
                'lat' => round((float) $point->lat, 6),
                'lng' => round((float) $point->lng, 6),
                'recorded_at' => $recordedAt->toIso8601String(),
            ];
        }

        if (is_array($point)) {
            $recordedAt = isset($point['recorded_at'])
                ? CarbonImmutable::parse((string) $point['recorded_at'])->toIso8601String()
                : '';

            return [
                'lat' => round((float) $point['lat'], 6),
                'lng' => round((float) $point['lng'], 6),
                'recorded_at' => $recordedAt,
            ];
        }

        throw new \InvalidArgumentException('Unsupported point type provided to TrackSimplifier.');
    }
}
