<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClinicianReportService
{
    public function create(User $user, array $options = [])
    {
        $periodLabel = $options['period'] ?? 'All time';
        $periodDays = $this->parsePeriodDays($periodLabel);

        $episodes = $user->episodes()
            ->orderByDesc('start_time')
            ->orderByDesc('created_at')
            ->get();

        if ($periodDays !== null) {
            $start = Carbon::now()->subDays($periodDays - 1)->startOfDay();

            $episodes = $episodes->filter(function (Episode $episode) use ($start) {
                $reference = $this->episodeReferenceDate($episode);

                return $reference && $reference->greaterThanOrEqualTo($start);
            })->values();
        }

        $triggers = $this->collectTriggerCounts($episodes);
        $locations = $this->collectLocationCounts($episodes);
        $symptoms = $this->collectSymptomCounts($episodes);

        $summary = [
            'total_episodes' => $episodes->count(),
            'average_intensity' => $this->averageIntensity($episodes),
            'median_duration' => $this->formatDuration($this->medianDurationMinutes($episodes)),
            'total_duration' => $this->formatDuration($this->totalEpisodeDuration($episodes)),
            'primary_trigger' => $triggers->first()['label'] ?? 'N/A',
            'primary_location' => $locations->first()['label'] ?? 'N/A',
            'generated_at' => now()->toDayDateTimeString(),
        ];

        $data = [
            'user' => $user,
            'clinician' => [
                'name' => $options['clinician_name'] ?? null,
                'email' => $options['clinician_email'] ?? null,
                'requested_by' => $options['requested_by'] ?? $user->name,
            ],
            'summary' => $summary,
            'intensity_distribution' => $this->intensityBuckets($episodes),
            'weekly_trend' => $this->buildWeeklySummary($episodes),
            'triggers' => array_slice($this->buildBreakdown($triggers), 0, 3),
            'locations' => array_slice($this->buildBreakdown($locations), 0, 3),
            'symptoms' => array_slice($this->buildBreakdown($symptoms), 0, 3),
            'recent_episodes' => $episodes->map(fn (Episode $episode) => [
                'date' => optional($this->episodeReferenceDate($episode))?->format('d M Y'),
                'intensity' => $episode->intensity !== null ? "{$episode->intensity}/10" : 'N/A',
                'triggers' => implode(', ', $episode->triggers ?? []),
                'notes' => $episode->notes ?? '-',
            ])->values()->all(),
            'logo_path' => public_path('logo-square.png'),
            'meta' => [
                'period' => $periodLabel,
            ],
        ];

        $pdf = Pdf::loadView('reports.clinician', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf;
    }

    private function buildBreakdown(Collection $items): array
    {
        $total = $items->sum('count');

        if ($total <= 0) {
            return $items->map(fn ($item) => [
                'label' => $item['label'],
                'count' => $item['count'],
                'percent' => 0,
            ])->values()->all();
        }

        return $items->map(fn ($item) => [
            'label' => $item['label'],
            'count' => $item['count'],
            'percent' => round(($item['count'] / $total) * 100, 1),
        ])->values()->all();
    }

    private function averageIntensity(Collection $episodes): string
    {
        $values = $episodes->pluck('intensity')->filter(fn ($value) => $value !== null);
        if ($values->isEmpty()) {
            return 'N/A';
        }

        return round($values->average(), 1) . '/10';
    }

    private function averageDurationMinutes(Collection $episodes): ?int
    {
        $durations = $episodes
            ->map(fn (Episode $episode) => $this->episodeDurationMinutes($episode))
            ->filter(fn (?int $value) => $value !== null)
            ->values();

        if ($durations->isEmpty()) {
            return null;
        }

        return (int) round($durations->avg());
    }

    private function totalEpisodeDuration(Collection $episodes): ?int
    {
        $total = $episodes
            ->map(fn (Episode $episode) => $this->episodeDurationMinutes($episode) ?? 0)
            ->sum();

        return $total ?: null;
    }

    private function formatDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return 'N/A';
        }

        if ($minutes < 60) {
            return $minutes . 'm';
        }

        $hours = floor($minutes / 60);
        $remaining = $minutes % 60;

        if ($remaining === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$remaining}m";
    }

    private function episodeDurationMinutes(Episode $episode): ?int
    {
        if ($episode->start_time && $episode->end_time) {
            return $episode->start_time->diffInMinutes($episode->end_time);
        }

        return null;
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

    private function collectSymptomCounts(Collection $episodes): Collection
    {
        $counts = [];

        foreach ($episodes as $episode) {
            $symptoms = $episode->symptoms ?? [];

            foreach ($symptoms as $symptom) {
                $normalized = Str::of($symptom ?? '')
                    ->trim()
                    ->lower()
                    ->__toString();

                if ($normalized === '') {
                    continue;
                }

                if (!isset($counts[$normalized])) {
                    $counts[$normalized] = [
                        'label' => Str::of($symptom)->trim()->__toString(),
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

    private function medianDurationMinutes(Collection $episodes): ?int
    {
        $durations = $episodes
            ->map(fn (Episode $episode) => $this->episodeDurationMinutes($episode))
            ->filter(fn (?int $value) => $value !== null)
            ->values();

        if ($durations->isEmpty()) {
            return null;
        }

        $sorted = $durations->sort()->values();
        $count = $sorted->count();
        $middle = (int) floor(($count - 1) / 2);

        if ($count % 2 === 0) {
            return (int) round(($sorted[$middle] + $sorted[$middle + 1]) / 2);
        }

        return $sorted[$middle];
    }

    private function intensityBuckets(Collection $episodes): array
    {
        $buckets = [
            'Mild (1-4)' => 0,
            'Moderate (5-7)' => 0,
            'Severe (8-10)' => 0,
        ];

        foreach ($episodes as $episode) {
            $value = $episode->intensity;
            if ($value === null) {
                continue;
            }

            if ($value <= 4) {
                $buckets['Mild (1-4)']++;
            } elseif ($value <= 7) {
                $buckets['Moderate (5-7)']++;
            } else {
                $buckets['Severe (8-10)']++;
            }
        }

        $total = array_sum($buckets);
        return array_map(fn ($count) => [
            'count' => $count,
            'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
        ], $buckets);
    }

    private function buildWeeklySummary(Collection $episodes): array
    {
        $grouped = $episodes
            ->groupBy(function (Episode $episode) {
                return $this->episodeReferenceDate($episode)?->format('o-W');
            })
            ->filter()
            ->sortBy(fn ($group, $key) => $key)
            ->map(fn ($group) => $group->count())
            ->slice(0, 4)
            ->values()
            ->all();

        return array_map(fn ($count, $index) => [
            'label' => "Week " . ($index + 1),
            'count' => $count,
        ], $grouped, array_keys($grouped));
    }

    private function episodeReferenceDate(Episode $episode): ?Carbon
    {
        return $episode->start_time ?? $episode->created_at;
    }

    private function parsePeriodDays(string $period): ?int
    {
        if (is_numeric($period)) {
            $value = (int) $period;
            return $value > 0 ? $value : null;
        }

        if (preg_match('/(\\d+)/', $period, $matches)) {
            $value = (int) $matches[1];
            return $value > 0 ? $value : null;
        }

        return null;
    }
}
