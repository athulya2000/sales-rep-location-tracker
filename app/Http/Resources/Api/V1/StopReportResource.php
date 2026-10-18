<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{rep_id: int, date: string, stops: iterable<mixed>} $resource
 */
final class StopReportResource extends JsonResource
{
    /**
     * Disable top-level wrapping for this resource.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array{rep_id: int, date: string, stops: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'rep_id' => (int) $this->resource['rep_id'],
            'date' => (string) $this->resource['date'],
            'stops' => StopResource::collection($this->resource['stops']),
        ];
    }
}
