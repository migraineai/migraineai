<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile', [
            'overview' => $this->buildThirtyDayOverview($user),
        ]);
    }

    private function buildThirtyDayOverview($user): array
    {
        if (!$user) {
            return $this->emptyOverview();
        }

        $rangeEnd = Carbon::now();
        $rangeStart = $rangeEnd->copy()->subDays(29)->startOfDay();

        $episodes = Episode::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($rangeStart) {
                $query->where('start_time', '>=', $rangeStart)
                    ->orWhere(function ($inner) use ($rangeStart) {
                        $inner->whereNull('start_time')->where('created_at', '>=', $rangeStart);
                    });
            })
            ->orderByDesc('start_time')
            ->orderByDesc('created_at')
            ->get();

        $filtered = $episodes->filter(function (Episode $episode) use ($rangeStart, $rangeEnd) {
            $reference = $this->episodeReferenceDate($episode);

            return $reference ? $reference->between($rangeStart, $rangeEnd, true) : false;
        });

        $totalEpisodes = $filtered->count();
        $averageIntensity = $this->averageIntensity($filtered);
        $totalDurationMinutes = $filtered->reduce(function (int $carry, Episode $episode) {
            return $carry + ($this->episodeDurationMinutes($episode) ?? 0);
        }, 0);

        $daysWithEpisodes = $filtered
            ->map(function (Episode $episode) {
                $reference = $this->episodeReferenceDate($episode);
                return $reference ? $reference->copy()->startOfDay()->toDateString() : null;
            })
            ->filter()
            ->unique()
            ->count();

        $totalDays = $rangeStart->diffInDays($rangeEnd) + 1;
        $painFreeDays = max($totalDays - $daysWithEpisodes, 0);
        $painFreePercentage = $totalDays > 0 ? round(($painFreeDays / $totalDays) * 100) : null;

        $topTrigger = $this->collectTriggerCounts($filtered)->first()['label'] ?? null;
        $commonLocation = $this->collectLocationCounts($filtered)->first()['label'] ?? null;

        return [
            'total_episodes' => $totalEpisodes,
            'average_intensity' => $averageIntensity,
            'total_duration_minutes' => $totalDurationMinutes,
            'pain_free_days' => $painFreeDays,
            'total_days' => $totalDays,
            'pain_free_percentage' => $painFreePercentage,
            'top_trigger' => $topTrigger,
            'common_location' => $commonLocation,
        ];
    }

    private function emptyOverview(): array
    {
        return [
            'total_episodes' => 0,
            'average_intensity' => null,
            'total_duration_minutes' => 0,
            'pain_free_days' => 30,
            'total_days' => 30,
            'pain_free_percentage' => 100,
            'top_trigger' => null,
            'common_location' => null,
        ];
    }

    private function episodeReferenceDate(Episode $episode): ?Carbon
    {
        if ($episode->start_time instanceof Carbon) {
            return $episode->start_time->copy();
        }

        if ($episode->created_at instanceof Carbon) {
            return $episode->created_at->copy();
        }

        return null;
    }

    private function episodeDurationMinutes(Episode $episode): ?int
    {
        if ($episode->start_time && $episode->end_time) {
            return $episode->start_time->diffInMinutes($episode->end_time);
        }

        return null;
    }

    private function averageIntensity(Collection $episodes): ?float
    {
        $values = $episodes
            ->pluck('intensity')
            ->filter(fn ($value) => $value !== null);

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->avg(), 1);
    }

    private function collectTriggerCounts(Collection $episodes): Collection
    {
        $counts = [];

        foreach ($episodes as $episode) {
            $triggers = $episode->triggers ?? [];
            foreach ($triggers as $trigger) {
                $normalized = Str::of($trigger ?? '')
                    ->trim()
                    ->lower()
                    ->__toString();

                if ($normalized === '') {
                    continue;
                }

                if (!isset($counts[$normalized])) {
                    $counts[$normalized] = [
                        'label' => Str::of($trigger)->trim()->__toString(),
                        'count' => 0,
                    ];
                }

                $counts[$normalized]['count']++;
            }
        }

        return collect($counts)
            ->sortByDesc('count')
            ->values();
    }

    private function collectLocationCounts(Collection $episodes): Collection
    {
        $counts = [];

        foreach ($episodes as $episode) {
            $location = $episode->pain_location;
            if (!$location) {
                continue;
            }

            $normalized = Str::of($location)
                ->trim()
                ->lower()
                ->__toString();

            if ($normalized === '') {
                continue;
            }

            if (!isset($counts[$normalized])) {
                $counts[$normalized] = [
                    'label' => Str::of($location)->trim()->__toString(),
                    'count' => 0,
                ];
            }

            $counts[$normalized]['count']++;
        }

        return collect($counts)
            ->sortByDesc('count')
            ->values();
    }
}
