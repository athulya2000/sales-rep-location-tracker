<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StopReportRequest;
use App\Http\Resources\Api\V1\StopReportResource;
use App\Models\Rep;
use App\Repositories\LocationPointRepository;
use App\Services\Tracking\StopDetector;

final class StopReportController extends Controller
{
    /**
     * Retrieve the stop report for a specific sales representative on a given date.
     */
    public function __invoke(
        Rep $rep,
        StopReportRequest $request,
        LocationPointRepository $repository,
        StopDetector $detector,
    ): StopReportResource {
        /** @var string $date */
        $date = (string) $request->validated('date');

        if ($repository->hasPrecomputedStops($rep->id, $date)) {
            $stops = $repository->getStopsForRepAndDate($rep->id, $date);
        } else {
            $points = $repository->getPointsLazyForRepAndDate($rep->id, $date);
            $stops = $detector->detect($points);
        }

        return new StopReportResource([
            'rep_id' => $rep->id,
            'date' => $date,
            'stops' => $stops,
        ]);
    }
}
