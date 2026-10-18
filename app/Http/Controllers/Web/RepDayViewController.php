<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Rep;
use App\Repositories\LocationPointRepository;
use App\Services\Tracking\StopDetector;
use App\Services\Tracking\TrackSimplifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class RepDayViewController extends Controller
{
    /**
     * Display the day playback dashboard with Leaflet map, track, and movement timeline.
     */
    public function __invoke(
        Rep $rep,
        Request $request,
        LocationPointRepository $repository,
        StopDetector $stopDetector,
        TrackSimplifier $trackSimplifier,
    ): View {
        $date = (string) ($request->query('date') ?? CarbonImmutable::now('UTC')->toDateString());

        $rawPoints = $repository->getPointsForRepAndDate($rep->id, $date);

        if ($repository->hasPrecomputedStops($rep->id, $date)) {
            $stops = $repository->getStopsForRepAndDate($rep->id, $date);
        } else {
            $stops = collect($stopDetector->detect($rawPoints));
        }

        $simplifiedTrack = $trackSimplifier->simplify($rawPoints->all());

        $timeline = $this->buildTimeline($rawPoints->all(), $stops->all());

        return view('reps.day', [
            'rep' => $rep,
            'date' => $date,
            'totalRawPoints' => $rawPoints->count(),
            'simplifiedPointCount' => count($simplifiedTrack),
            'track' => $simplifiedTrack,
            'stops' => $stops,
            'timeline' => $timeline,
        ]);
    }

    /**
     * @param  list<mixed>  $points
     * @param  list<mixed>  $stops
     * @return list<array{type: string, title: string, subtitle: string, started_at: string, ended_at: string, duration_text: string, lat: float|null, lng: float|null}>
     */
    private function buildTimeline(array $points, array $stops): array
    {
        if (empty($points)) {
            return [];
        }

        $firstPointTime = CarbonImmutable::parse($points[0]->recorded_at ?? $points[0]['recorded_at']);
        $lastPointTime = CarbonImmutable::parse($points[count($points) - 1]->recorded_at ?? $points[count($points) - 1]['recorded_at']);

        // Normalize stops
        $normalizedStops = [];
        foreach ($stops as $s) {
            $started = CarbonImmutable::parse(is_array($s) ? $s['started_at'] : $s->started_at);
            $ended = CarbonImmutable::parse(is_array($s) ? $s['ended_at'] : $s->ended_at);
            $lat = (float) (is_array($s) ? $s['lat'] : $s->lat);
            $lng = (float) (is_array($s) ? $s['lng'] : $s->lng);
            $durationSec = (int) (is_array($s) ? $s['duration_seconds'] : $s->duration_seconds);

            $normalizedStops[] = [
                'started' => $started,
                'ended' => $ended,
                'lat' => $lat,
                'lng' => $lng,
                'duration_seconds' => $durationSec,
            ];
        }

        usort($normalizedStops, fn ($a, $b) => $a['started']->getTimestamp() <=> $b['started']->getTimestamp());

        $timeline = [];
        $cursorTime = $firstPointTime;

        foreach ($normalizedStops as $idx => $stop) {
            if ($stop['started']->greaterThan($cursorTime)) {
                $movingSec = $stop['started']->getTimestamp() - $cursorTime->getTimestamp();
                if ($movingSec >= 60) {
                    $timeline[] = [
                        'type' => 'moving',
                        'title' => 'Walking / Moving',
                        'subtitle' => 'Representative in transit',
                        'started_at' => $cursorTime->format('H:i'),
                        'ended_at' => $stop['started']->format('H:i'),
                        'duration_text' => round($movingSec / 60).' mins',
                        'lat' => null,
                        'lng' => null,
                    ];
                }
            }

            $mins = round($stop['duration_seconds'] / 60);
            $timeline[] = [
                'type' => 'stop',
                'title' => 'Stationary Stop #'.($idx + 1),
                'subtitle' => 'Stationary within 50m radius',
                'started_at' => $stop['started']->format('H:i'),
                'ended_at' => $stop['ended']->format('H:i'),
                'duration_text' => "{$mins} mins",
                'lat' => $stop['lat'],
                'lng' => $stop['lng'],
            ];

            $cursorTime = $stop['ended'];
        }

        if ($lastPointTime->greaterThan($cursorTime)) {
            $remainingSec = $lastPointTime->getTimestamp() - $cursorTime->getTimestamp();
            if ($remainingSec >= 60) {
                $timeline[] = [
                    'type' => 'moving',
                    'title' => 'Walking / Moving',
                    'subtitle' => 'Final shift movement',
                    'started_at' => $cursorTime->format('H:i'),
                    'ended_at' => $lastPointTime->format('H:i'),
                    'duration_text' => round($remainingSec / 60).' mins',
                    'lat' => null,
                    'lng' => null,
                ];
            }
        }

        return $timeline;
    }
}
