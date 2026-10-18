<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\LocationPointRepository;
use App\Services\Tracking\StopDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ComputeStopsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $repId,
        public readonly string $date,
    ) {}

    /**
     * Execute the job to compute and store stops for a rep on a specific date.
     */
    public function handle(LocationPointRepository $repository, StopDetector $detector): void
    {
        $points = $repository->getPointsLazyForRepAndDate($this->repId, $this->date);
        $stops = $detector->detect($points);

        $repository->replaceStopsForDate($this->repId, $this->date, $stops);
    }
}
