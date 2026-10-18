<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ZoneReportRequest;
use App\Http\Resources\Api\V1\ZoneRepResource;
use App\Models\Rep;
use App\Repositories\LocationPointRepository;
use App\Services\Tracking\ZoneAnalyzer;
use App\Support\Geo;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ZoneReportController extends Controller
{
    /**
     * Retrieve all sales representatives who entered a designated zone on a date,
     * along with their total time spent inside the zone.
     */
    public function __invoke(
        ZoneReportRequest $request,
        LocationPointRepository $repository,
        ZoneAnalyzer $analyzer,
    ): AnonymousResourceCollection {
        /** @var string $date */
        $date = (string) $request->validated('date');

        /** @var list<array{0: float|int|string, 1: float|int|string}|list<float>> $polygon */
        $polygon = $request->validated('coordinates');

        // Step 1: Bounding box pre-filter using database index (recorded_date, lat, lng)
        $bbox = Geo::boundingBox($polygon);
        $candidateRepIds = $repository->findCandidateRepIdsInBoundingBox(
            $date,
            $bbox['min_lat'],
            $bbox['max_lat'],
            $bbox['min_lng'],
            $bbox['max_lng']
        );

        if (empty($candidateRepIds)) {
            return ZoneRepResource::collection([]);
        }

        $reps = Rep::query()->whereIn('id', $candidateRepIds)->get()->keyBy('id');

        $results = [];

        // Step 2 & 3: Compute exact in-polygon time for each candidate
        foreach ($candidateRepIds as $repId) {
            $rep = $reps->get($repId);
            if ($rep === null) {
                continue;
            }

            $points = $repository->getPointsForRepAndDate($repId, $date);
            $secondsInZone = $analyzer->calculateTimeInZone($points, $polygon);

            if ($secondsInZone > 0) {
                $results[] = [
                    'rep_id' => $rep->id,
                    'name' => $rep->name,
                    'seconds_in_zone' => $secondsInZone,
                ];
            }
        }

        // Sort by time descending
        usort($results, fn (array $a, array $b): int => $b['seconds_in_zone'] <=> $a['seconds_in_zone']);

        return ZoneRepResource::collection($results);
    }
}
