<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLocationBatchRequest;
use App\Jobs\ComputeStopsJob;
use App\Repositories\LocationPointRepository;
use Illuminate\Http\JsonResponse;

final class LocationBatchController extends Controller
{
    /**
     * Ingest a batch of location points for a sales representative.
     */
    public function __invoke(
        StoreLocationBatchRequest $request,
        LocationPointRepository $repository,
    ): JsonResponse {
        /** @var int $repId */
        $repId = (int) $request->validated('rep_id');

        /** @var list<array{lat: float|int|string, lng: float|int|string, recorded_at: string, accuracy?: int|null}> $points */
        $points = $request->validated('points');

        $result = $repository->insertBatch($repId, $points);

        foreach ($result['affected_dates'] as $date) {
            ComputeStopsJob::dispatch($repId, $date);
        }

        return response()->json([
            'accepted' => $result['accepted'],
            'inserted' => $result['inserted'],
            'duplicates' => $result['duplicates'],
        ], 202);
    }
}
