<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EpisodeInsightsService
{
    private const VALID_RANGES = [7, 30, 90];

    public function build(User $user, int $range = 30): array
    {
        $range = in_array($range, self::VALID_RANGES, true) ? $range : 30;

        $rangeStart = Carbon::now()->subDays($range - 1)->startOfDay();
        $rangeEnd = Carbon::now()->endOfDay();

        $episodes = Episode::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($rangeStart) {
                $query->whereNull('start_time')->orWhere('start_time', '>=', $rangeStart);
            })
            ->orderByDesc('start_time')
            ->get();

        return [
            'range' => $range,
            'episodes' => $episodes
                ->map(fn (Episode $episode) => $this->transformEpisode($episode))
                ->values()
                ->all(),
            'summary' => $this->buildSummary($episodes, $range, $rangeStart),
            'sparklines' => $this->buildSparklines($episodes, $range, $rangeStart),
            'period_days' => $this->buildPeriodDays($user, $rangeStart, $rangeEnd),
        ];
    }

    private function transformEpisode(Episode $episode): array
    {
        return [
            'id' => $episode->id,
            'start_time' => optional($episode->start_time)->toIso8601String(),
            'end_time' => optional($episode->end_time)->toIso8601String(),
            'intensity' => $episode->intensity,
            'pain_location' => $episode->pain_location,
            'aura' => $episode->aura,
            'symptoms' => $episode->symptoms,
            'triggers' => $episode->triggers,
            'what_you_tried' => $episode->what_you_tried,
            'notes' => $episode->notes,
            'transcript_text' => $episode->transcript_text,
            'created_at' => $episode->created_at?->toIso8601String(),
        ];
    }

    private function buildSummary(Collection $episodes, int $range, Carbon $rangeStart): array
    {
        $total = $episodes->count();
        $avgIntensity = $episodes
            ->pluck('intensity')
            ->filter(fn ($value) => $value !== null)
            ->average();

        $totalDurationMinutes = $episodes->reduce(function (int $carry, Episode $episode) {
            if ($episode->start_time && $episode->end_time) {
                return $carry + $episode->start_time->diffInMinutes($episode->end_time);
            }

            return $carry;
        }, 0);

        $totalDays = collect(range(0, $range - 1))
            ->map(fn (int $offset) => $rangeStart->copy()->addDays($offset)->toDateString());

        $daysWithEpisodes = $episodes
            ->filter(fn (Episode $episode) => $episode->start_time !== null)
            ->map(fn (Episode $episode) => $episode->start_time->toDateString())
            ->unique();

        $painFreeDays = max($totalDays->count() - $daysWithEpisodes->count(), 0);
        $painFreePercent = $totalDays->count() > 0 ? round(($painFreeDays / $totalDays->count()) * 100, 1) : null;

        return [
            'total_episodes' => $total,
            'average_intensity' => $avgIntensity ? round($avgIntensity, 1) : null,
            'total_duration_hours' => round($totalDurationMinutes / 60, 1),
            'pain_free_days_percent' => $painFreePercent,
        ];
    }

    private function buildSparklines(Collection $episodes, int $range, Carbon $rangeStart): array
    {
        $dateRange = collect(range(0, max(6, $range - 1)))
            ->map(fn (int $offset) => $rangeStart->copy()->addDays($offset))
            ->keyBy(fn (Carbon $date) => $date->toDateString());

        $groupedByDate = $episodes
            ->filter(fn (Episode $episode) => $episode->start_time !== null)
            ->groupBy(fn (Episode $episode) => $episode->start_time->toDateString());

        $attackCounts = $dateRange->map(function (Carbon $date, string $key) use ($groupedByDate) {
            return [
                'date' => $date->toDateString(),
                'count' => $groupedByDate->has($key) ? $groupedByDate[$key]->count() : 0,
            ];
        })->values();

        $intensityTrend = $dateRange->map(function (Carbon $date, string $key) use ($groupedByDate) {
            $items = $groupedByDate->get($key);
            if (!$items) {
                return [
                    'date' => $date->toDateString(),
                    'average_intensity' => null,
                ];
            }

            $avg = $items
                ->pluck('intensity')
                ->filter(fn ($value) => $value !== null)
                ->average();

            return [
                'date' => $date->toDateString(),
                'average_intensity' => $avg ? round($avg, 1) : null,
            ];
        })->values();

        return [
            'attack_count' => $attackCounts->values()->all(),
            'average_intensity' => $intensityTrend->values()->all(),
        ];
    }

    private function buildPeriodDays(User $user, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if (!$user->cycle_tracking_enabled || !$user->last_period_start_date) {
            return [];
        }

        $cycleLength = $user->cycle_length_days ?? 28;
        $periodLength = $user->period_length_days ?? 5;

        if ($cycleLength <= 0 || $periodLength <= 0) {
            return [];
        }

        $periodStart = $user->last_period_start_date->copy()->startOfDay();

        while ($periodStart->gt($rangeStart)) {
            $periodStart->subDays($cycleLength);
        }

        $days = [];
        $currentStart = $periodStart->copy();

        while ($currentStart->lte($rangeEnd)) {
            for ($i = 0; $i < $periodLength; $i++) {
                $day = $currentStart->copy()->addDays($i);
                if ($day->betweenIncluded($rangeStart, $rangeEnd)) {
                    $days[] = $day->toDateString();
                }
            }
            $currentStart->addDays($cycleLength);
        }

        return array_values(array_unique($days));
    }
}
