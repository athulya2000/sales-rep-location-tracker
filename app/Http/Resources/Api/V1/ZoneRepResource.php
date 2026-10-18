<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{rep_id: int, name: string, seconds_in_zone: int} $resource
 */
final class ZoneRepResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{rep_id: int, name: string, seconds_in_zone: int, minutes_in_zone: float}
     */
    public function toArray(Request $request): array
    {
        $seconds = (int) $this->resource['seconds_in_zone'];

        return [
            'rep_id' => (int) $this->resource['rep_id'],
            'name' => (string) $this->resource['name'],
            'seconds_in_zone' => $seconds,
            'minutes_in_zone' => round($seconds / 60, 2),
        ];
    }
}
