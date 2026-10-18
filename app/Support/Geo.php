<?php

declare(strict_types=1);

namespace App\Support;

final class Geo
{
    /**
     * Earth radius in meters.
     */
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Calculate great-circle distance between two coordinates in meters using the Haversine formula.
     */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);

        $a = sin($dLat / 2) ** 2 +
            cos($lat1Rad) * cos($lat2Rad) * (sin($dLng / 2) ** 2);

        $aClamped = min(1.0, max(0.0, $a));
        $c = 2 * atan2(sqrt($aClamped), sqrt(1.0 - $aClamped));

        return self::EARTH_RADIUS_METERS * $c;
    }

    /**
     * Determine if a point is inside a polygon using ray casting (Jordan curve theorem).
     *
     * @param  list<array{0: float|int|string, 1: float|int|string}|list<float>>  $polygon  Vertices as [[lat, lng], ...]
     */
    public static function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $count = count($polygon);
        if ($count < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $latI = (float) $polygon[$i][0];
            $lngI = (float) $polygon[$i][1];
            $latJ = (float) $polygon[$j][0];
            $lngJ = (float) $polygon[$j][1];

            // Check if point is on an exact vertex
            if (abs($lat - $latI) < 1e-9 && abs($lng - $lngI) < 1e-9) {
                return true;
            }

            // Ray casting along lng axis for given latitude
            $intersectsLat = (($latI > $lat) !== ($latJ > $lat));
            if ($intersectsLat) {
                $intersectLng = $lngI + ($lat - $latI) * ($lngJ - $lngI) / ($latJ - $latI);
                if (abs($lng - $intersectLng) < 1e-9) {
                    return true; // Point lies on edge
                }
                if ($lng < $intersectLng) {
                    $inside = ! $inside;
                }
            }
        }

        return $inside;
    }

    /**
     * Compute axis-aligned bounding box for a polygon.
     *
     * @param  list<array{0: float|int|string, 1: float|int|string}|list<float>>  $polygon
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public static function boundingBox(array $polygon): array
    {
        if (empty($polygon)) {
            return [
                'min_lat' => 0.0,
                'max_lat' => 0.0,
                'min_lng' => 0.0,
                'max_lng' => 0.0,
            ];
        }

        $minLat = INF;
        $maxLat = -INF;
        $minLng = INF;
        $maxLng = -INF;

        foreach ($polygon as $point) {
            $lat = (float) $point[0];
            $lng = (float) $point[1];

            if ($lat < $minLat) {
                $minLat = $lat;
            }
            if ($lat > $maxLat) {
                $maxLat = $lat;
            }
            if ($lng < $minLng) {
                $minLng = $lng;
            }
            if ($lng > $maxLng) {
                $maxLng = $lng;
            }
        }

        return [
            'min_lat' => (float) $minLat,
            'max_lat' => (float) $maxLat,
            'min_lng' => (float) $minLng,
            'max_lng' => (float) $maxLng,
        ];
    }
}
