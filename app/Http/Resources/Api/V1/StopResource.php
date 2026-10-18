<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Stop;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Stop|array{lat: float, lng: float, started_at: string, ended_at: string, duration_seconds: int} $resource
 */
final class StopResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{lat: float, lng: float, started_at: string, ended_at: string, duration_minutes: float, duration_seconds: int}
     */
    public function toArray(Request $request): array
    {
        $isArr = is_array($this->resource);

        $lat = $isArr ? (float) $this->resource['lat'] : (float) $this->resource->lat;
        $lng = $isArr ? (float) $this->resource['lng'] : (float) $this->resource->lng;
        $startedRaw = $isArr ? $this->resource['started_at'] : $this->resource->started_at;
        $endedRaw = $isArr ? $this->resource['ended_at'] : $this->resource->ended_at;
        $durationSeconds = $isArr ? (int) $this->resource['duration_seconds'] : (int) $this->resource->duration_seconds;

        $startedAt = CarbonImmutable::parse($startedRaw)->toIso8601String();
        $endedAt = CarbonImmutable::parse($endedRaw)->toIso8601String();

        return [
            'lat' => $lat,
            'lng' => $lng,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => round($durationSeconds / 60, 2),
            'duration_seconds' => $durationSeconds,
        ];
    }
}
