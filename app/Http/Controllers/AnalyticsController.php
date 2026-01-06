<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $episodes = Episode::query()
            ->where('user_id', $user->id)
            ->orderByDesc('start_time')
            ->orderByDesc('created_at')
            ->get();

        $overview = $this->buildOverviewData($episodes, $user);
        $analysis = $this->buildAnalysisData($episodes);

        return Inertia::render('Analytics', [
            'overview' => $overview,
            'analysis' => $analysis,
        ]);
    }

    private function buildOverviewData(Collection $episodes, $user): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $previousMonthStart = $startOfMonth->copy()->subMonth();
        $previousMonthEnd = $startOfMonth->copy()->subSecond();

        $currentMonthEpisodes = $episodes->filter(function (Episode $episode) use ($startOfMonth, $now) {
            $reference = $this->episodeReferenceDate($episode);
            if (!$reference) {
                return false;
            }

            return $reference->between($startOfMonth, $now, true);
        });

        $previousMonthEpisodes = $episodes->filter(function (Episode $episode) use ($previousMonthStart, $previousMonthEnd) {
            $reference = $this->episodeReferenceDate($episode);
            if (!$reference) {
                return false;
            }

            return $reference->between($previousMonthStart, $previousMonthEnd, true);
        });

        $baselineMonths = collect(range(1, 6))
            ->map(function (int $offset) use ($startOfMonth, $episodes) {
                $monthStart = $startOfMonth->copy()->subMonths($offset);
                $monthEnd = $monthStart->copy()->endOfMonth();

                $monthEpisodes = $episodes->filter(function (Episode $episode) use ($monthStart, $monthEnd) {
                    $reference = $this->episodeReferenceDate($episode);
                    if (!$reference) {
                        return false;
                    }

                    return $reference->between($monthStart, $monthEnd, true);
                });

                return [
                    'count' => $monthEpisodes->count(),
                    'average_intensity' => $this->averageIntensity($monthEpisodes),
                ];
            })
            ->filter(fn (array $month) => $month['count'] > 0 || $month['average_intensity'] !== null);

        $baselineEpisodesPerMonth = $baselineMonths->isEmpty()
            ? null
            : round($baselineMonths->avg('count'), 1);

        $baselineIntensity = $baselineMonths->filter(fn ($month) => $month['average_intensity'] !== null)
            ->avg('average_intensity');

        $baselineIntensity = $baselineIntensity !== null ? round($baselineIntensity, 1) : null;

        $currentMonthCount = $currentMonthEpisodes->count();
        $currentMonthIntensity = $this->averageIntensity($currentMonthEpisodes);

        $overviewSummary = [
            [
                'label' => 'Total Episodes',
                'value' => $episodes->count(),
                'value_type' => 'count',
                'helper' => 'All time recorded',
            ],
            [
                'label' => 'This Month',
                'value' => $currentMonthCount,
                'value_type' => 'count',
                'helper' => $this->formatChangeHelper($currentMonthCount, $previousMonthEpisodes->count()),
            ],
            [
                'label' => 'Avg Intensity',
                'value' => $this->averageIntensity($episodes),
                'value_type' => 'intensity',
                'helper' => 'Pain severity scale',
            ],
            [
                'label' => 'Avg Duration',
                'value' => $this->averageDurationMinutes($episodes),
                'value_type' => 'minutes',
                'helper' => 'Episode length',
            ],
        ];

        $baselineComparisons = [
            [
                'label' => 'Episodes / Month',
                'baseline_value' => $baselineEpisodesPerMonth,
                'current_value' => $currentMonthCount,
                'value_type' => 'count',
                'status' => $this->determineStatus($currentMonthCount, $baselineEpisodesPerMonth, 'lower-is-better'),
                'delta' => $this->formatDeltaDescription($currentMonthCount, $baselineEpisodesPerMonth, 'episodes'),
            ],
            [
                'label' => 'Average Intensity',
                'baseline_value' => $baselineIntensity,
                'current_value' => $currentMonthIntensity,
                'value_type' => 'intensity',
                'status' => $this->determineStatus($currentMonthIntensity, $baselineIntensity, 'lower-is-better'),
                'delta' => $this->formatDeltaDescription($currentMonthIntensity, $baselineIntensity, 'intensity'),
            ],
        ];

        $triggerCounts = $this->collectTriggerCounts($episodes);
        $symptomCounts = $this->collectSymptomCounts($episodes);
        $locationCounts = $this->collectLocationCounts($episodes);

        $triggerLegend = $this->buildLegendFromCounts(
            $triggerCounts,
            ['legend-primary', 'legend-secondary', 'legend-tertiary', 'legend-quaternary', 'legend-muted']
        );
        $symptomLegend = $this->buildLegendFromCounts(
            $symptomCounts,
            ['legend-primary', 'legend-secondary', 'legend-tertiary', 'legend-quaternary', 'legend-muted']
        );

        $locationLegend = $this->buildLegendFromCounts(
            $locationCounts,
            ['legend-primary', 'legend-secondary', 'legend-tertiary', 'legend-quaternary', 'legend-muted']
        );

        $triggerBreakdown = $this->buildBreakdownFromCounts(
            $triggerCounts,
            ['legend-primary', 'legend-secondary', 'legend-tertiary', 'legend-quaternary', 'legend-muted']
        );
        $symptomBreakdown = $this->buildBreakdownFromCounts(
            $symptomCounts,
            ['legend-primary', 'legend-secondary', 'legend-tertiary', 'legend-quaternary', 'legend-muted']
        );

        $locationBreakdown = $this->buildBreakdownFromCounts(
            $locationCounts,
            ['legend-primary', 'legend-secondary', 'legend-tertiary', 'legend-quaternary', 'legend-muted']
        );

        $locationHeatmap = $locationCounts
            ->take(3)
            ->values()
            ->map(function (array $item, int $index) use ($locationCounts) {
                $total = $locationCounts->sum('count');

                return [
                    'label' => $item['label'],
                    'count' => $item['count'],
                    'percent' => $total > 0 ? round(($item['count'] / $total) * 100, 1) : 0,
                    'intensity' => match ($index) {
                        0 => 'high',
                        1 => 'medium',
                        default => 'low',
                    },
                ];
            })
            ->all();

        $phaseDurations = $this->buildPhaseDurations($episodes, $user);
        $heatmapEpisodes = $this->prepareHeatmapEpisodes($episodes);
        $medicalProfile = $this->buildMedicalProfileData($user);

        return [
            'baseline_comparisons' => $baselineComparisons,
            'summary_cards' => $overviewSummary,
            'trigger_legend' => $triggerLegend,
            'symptom_legend' => $symptomLegend,
            'location_legend' => $locationLegend,
            'trigger_breakdown' => $triggerBreakdown,
            'symptom_breakdown' => $symptomBreakdown,
            'location_breakdown' => $locationBreakdown,
            'location_heatmap' => $locationHeatmap,
            'phase_durations' => $phaseDurations,
            'heatmap_episodes' => $heatmapEpisodes,
            'medical_profile' => $medicalProfile,
        ];
    }

    private function buildAnalysisData(Collection $episodes): array
    {
        $periods = [
            30 => 'Last 30 Days',
            60 => 'Last 60 Days',
            90 => 'Last 90 Days',
        ];

        $analysis = collect($periods)->mapWithKeys(function (string $label, int $days) use ($episodes) {
            $start = Carbon::now()->subDays($days - 1)->startOfDay();

            $periodEpisodes = $episodes->filter(function (Episode $episode) use ($start) {
                $reference = $this->episodeReferenceDate($episode);
                if (!$reference) {
                    return false;
                }

                return $reference->greaterThanOrEqualTo($start);
            });

            $totalEpisodes = $periodEpisodes->count();
            $totalDurationMinutes = $periodEpisodes->reduce(function (int $carry, Episode $episode) {
                $duration = $this->episodeDurationMinutes($episode);
                return $carry + ($duration ?? 0);
            }, 0);

            $episodesWithDuration = $periodEpisodes->filter(fn (Episode $episode) => $this->episodeDurationMinutes($episode) !== null);
            $averageDurationMinutes = $episodesWithDuration->isEmpty()
                ? null
                : round(
                    $episodesWithDuration
                        ->map(fn (Episode $episode) => $this->episodeDurationMinutes($episode) ?? 0)
                        ->avg(),
                    1
                );

            $triggerCounts = $this->collectTriggerCounts($periodEpisodes);
            $locationCounts = $this->collectLocationCounts($periodEpisodes);

            $triggerBreakdown = $triggerCounts->take(5)->map(function (array $item) {
                return [
                    'label' => $item['label'],
                    'value' => $item['count'],
                ];
            })->values();

            $periodEpisodesList = $periodEpisodes
                ->sortByDesc(fn (Episode $episode) => $this->episodeReferenceDate($episode)?->timestamp ?? 0)
                ->take(12)
                ->map(function (Episode $episode) {
                    $reference = $this->episodeReferenceDate($episode);
                    $endTime = $episode->end_time;
                    $duration = $this->episodeDurationMinutes($episode);

                    return [
                        'id' => $episode->id,
                        'date' => $reference ? $reference->format('d/m/Y') : 'N/A',
                        'time_range' => $reference
                            ? $reference->format('H:i') . ($endTime ? ' - ' . $endTime->format('H:i') : '')
                            : 'N/A',
                        'duration' => $duration !== null ? $this->formatDurationString($duration) : 'N/A',
                        'intensity_display' => $episode->intensity !== null ? $episode->intensity . '/10' : 'N/A',
                        'location' => $episode->pain_location ?? 'N/A',
                        'triggers' => array_values($episode->triggers ?? []),
                        'start_time_iso' => optional($episode->start_time)?->toIso8601String(),
                        'end_time_iso' => optional($episode->end_time)?->toIso8601String(),
                        'intensity_value' => $episode->intensity,
                        'pain_location_value' => $episode->pain_location,
                        'notes' => $episode->notes,
                        'what_you_tried' => $episode->what_you_tried,
                        'aura' => $episode->aura,
                        'symptoms' => array_values($episode->symptoms ?? []),
                    ];
                })
                ->values();

            return [
                (string)$days => [
                    'label' => $label,
                    'metrics' => [
                        'total_episodes' => $totalEpisodes,
                        'average_intensity' => $this->averageIntensity($periodEpisodes),
                        'total_duration_minutes' => $totalDurationMinutes,
                        'average_duration_minutes' => $averageDurationMinutes,
                        'primary_trigger' => $triggerCounts->first()['label'] ?? null,
                        'common_location' => $locationCounts->first()['label'] ?? null,
                    ],
                    'trigger_breakdown' => $triggerBreakdown->values()->all(),
                    'episodes' => $periodEpisodesList->values()->all(),
                    'episode_count' => $totalEpisodes,
                ],
            ];
        });

        return [
            'periods' => $analysis->toArray(),
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

    private function averageDurationMinutes(Collection $episodes): ?float
    {
        $durations = $episodes
            ->map(fn (Episode $episode) => $this->episodeDurationMinutes($episode))
            ->filter(fn ($duration) => $duration !== null);

        if ($durations->isEmpty()) {
            return null;
        }

        return round($durations->avg(), 1);
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

    private function prepareHeatmapEpisodes(Collection $episodes): array
    {
        return $episodes
            ->map(function (Episode $episode) {
                return [
                    'id' => $episode->id,
                    'intensity' => $episode->intensity,
                    'pain_location' => $episode->pain_location,
                    'start_time' => optional($episode->start_time)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
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

    private function buildBreakdownFromCounts(Collection $counts, array $palette): array
    {
        $total = $counts->sum('count');

        return $counts
            ->take(count($palette))
            ->values()
            ->map(function (array $item, int $index) use ($palette, $total) {
                $count = $item['count'];

                return [
                    'label' => $item['label'],
                    'count' => $count,
                    'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                    'color' => $palette[$index] ?? 'legend-muted',
                ];
            })
            ->all();
    }

    private function buildLegendFromCounts(Collection $counts, array $colorPalette): array
    {
        return $counts
            ->take(count($colorPalette))
            ->values()
            ->map(function (array $item, int $index) use ($colorPalette) {
                return [
                    'label' => $item['label'],
                    'color' => $colorPalette[$index] ?? 'legend-muted',
                ];
            })
            ->all();
    }

    private function formatDurationString(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return $remaining . 'm';
        }

        return $hours . 'h ' . $remaining . 'm';
    }

    private function determineStatus(?float $current, ?float $baseline, string $direction): ?string
    {
        if ($current === null || $baseline === null) {
            return null;
        }

        $difference = $current - $baseline;

        if (abs($difference) < 0.05) {
            return 'Stable';
        }

        $isImprovement = $direction === 'lower-is-better' ? $difference <= 0 : $difference >= 0;

        return $isImprovement ? 'Improved' : 'Higher';
    }

    private function formatDeltaDescription(?float $current, ?float $baseline, string $noun): ?string
    {
        if ($current === null || $baseline === null) {
            return null;
        }

        $difference = $current - $baseline;

        if (abs($difference) < 0.05) {
            return 'In line with your typical baseline';
        }

        $noun = Str::of($noun)->singular();

        $absolute = round(abs($difference), 1);
        $percent = $baseline != 0 ? round(($difference / $baseline) * 100, 1) : null;

        if ($difference < 0) {
            $description = "{$absolute} fewer {$noun}";
        } else {
            $description = "{$absolute} more {$noun}";
        }

        if ($percent !== null) {
            $description .= " (" . ($difference < 0 ? '-' : '+') . abs($percent) . "%)";
        }

        return $description;
    }

    private function formatChangeHelper(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current === 0 ? 'No change vs. last month' : 'No prior month data';
        }

        if ($current === $previous) {
            return 'No change vs. last month';
        }

        $difference = $current - $previous;
        $percent = round(($difference / max($previous, 1)) * 100, 1);
        $symbol = $difference > 0 ? '▲' : '▼';

        return "{$symbol} " . abs($percent) . '% vs. last month';
    }

    private function buildPhaseDurations(Collection $episodes, $user): array
    {
        if (
            !$user
            || !$user->cycle_tracking_enabled
            || !$user->last_period_start_date
            || !$user->cycle_length_days
        ) {
            return [];
        }

        $cycleLength = max($user->cycle_length_days ?? 28, 1);
        $periodLength = max($user->period_length_days ?? 5, 1);

        if ($cycleLength <= 0) {
            return [];
        }

        $ovulationEstimate = max(min($cycleLength - 14, $cycleLength), $periodLength + 1);
        $ovulationEnd = min($ovulationEstimate + 1, $cycleLength);
        $lutealStart = min($ovulationEnd + 1, $cycleLength);

        $phases = [
            'Menstrual' => [
                'range' => [1, min($periodLength, $cycleLength)],
                'totals' => ['minutes' => 0, 'count' => 0],
            ],
            'Follicular' => [
                'range' => [
                    $periodLength + 1,
                    max($periodLength + 1, min($ovulationEstimate - 1, $cycleLength)),
                ],
                'totals' => ['minutes' => 0, 'count' => 0],
            ],
            'Ovulation' => [
                'range' => [
                    min($ovulationEstimate, $cycleLength),
                    max(min($ovulationEnd, $cycleLength), min($ovulationEstimate, $cycleLength)),
                ],
                'totals' => ['minutes' => 0, 'count' => 0],
            ],
            'Luteal' => [
                'range' => [
                    max($lutealStart, 1),
                    $cycleLength,
                ],
                'totals' => ['minutes' => 0, 'count' => 0],
            ],
        ];

        $cycleStart = $user->last_period_start_date->copy()->startOfDay();

        foreach ($episodes as $episode) {
            $reference = $this->episodeReferenceDate($episode)?->copy();
            $duration = $this->episodeDurationMinutes($episode);

            if (!$reference || $duration === null) {
                continue;
            }

            $referenceStart = $reference->copy()->startOfDay();
            $daysDiff = $cycleStart->diffInDays($referenceStart, false);
            $cycleDayIndex = (($daysDiff % $cycleLength) + $cycleLength) % $cycleLength;
            $cycleDay = $cycleDayIndex + 1;

            foreach ($phases as $phaseName => &$phaseData) {
                [$start, $end] = $phaseData['range'];

                if ($start > $end) {
                    continue;
                }

                if ($cycleDay >= $start && $cycleDay <= $end) {
                    $phaseData['totals']['minutes'] += $duration;
                    $phaseData['totals']['count'] += 1;
                    break;
                }
            }
            unset($phaseData);
        }

        return collect($phases)
            ->map(function (array $phase, string $label) {
                [$start, $end] = $phase['range'];
                $count = $phase['totals']['count'];
                $average = $count > 0 ? round($phase['totals']['minutes'] / $count, 1) : null;
                $rangeLabel = $start === $end ? "Day {$start}" : "Days {$start}-{$end}";

                return [
                    'phase' => $label,
                    'range' => $rangeLabel,
                    'average_minutes' => $average,
                    'count' => $count,
                ];
            })
            ->values()
            ->all();
    }

    private function buildMedicalProfileData($user): ?array
    {
        $answers = $user->onboarding_answers ?? null;

        if (!is_array($answers) || empty($answers)) {
            return null;
        }

        $questions = $this->onboardingQuestions();

        if ($questions->isEmpty()) {
            return null;
        }

        $attackDuration = $this->resolveOptionLabel($questions->get('q2_duration'), $answers['q2_duration'] ?? null);
        $painLocation = $this->resolveOptionLabel($questions->get('q4_location'), $answers['q4_location'] ?? null);
        $aura = $this->resolveAuraDescription($questions->get('q8_aura'), $answers['q8_aura'] ?? null);

        if (!$attackDuration && !$painLocation && !$aura) {
            return null;
        }

        return [
            'attack_duration' => $attackDuration,
            'pain_location' => $painLocation,
            'aura' => $aura,
        ];
    }

    private function onboardingQuestions(): Collection
    {
        static $questions;

        if ($questions instanceof Collection) {
            return $questions;
        }

        $path = base_path('ref/onboarding.json');

        if (!is_readable($path)) {
            return collect();
        }

        $contents = file_get_contents($path);
        $data = json_decode($contents ?: '', true);

        if (!is_array($data)) {
            return collect();
        }

        $questions = collect(data_get($data, 'onboarding_flow.sections', []))
            ->flatMap(function (array $section) {
                return $section['questions'] ?? [];
            })
            ->keyBy('id');

        return $questions;
    }

    private function resolveOptionLabel(?array $question, $value): ?string
    {
        if (!$question || $value === null) {
            return null;
        }

        foreach ($question['options'] ?? [] as $option) {
            if (($option['value'] ?? null) === $value) {
                return $option['label'] ?? null;
            }
        }

        return null;
    }

    private function resolveAuraDescription(?array $question, $value): ?string
    {
        if (!$question) {
            return null;
        }

        $values = [];

        if (is_array($value)) {
            $values = $value;
        } elseif ($value !== null && $value !== '') {
            $values = [$value];
        }

        if (empty($values)) {
            return null;
        }

        $hasNone = in_array('none', $values, true);

        if ($hasNone && count($values) === 1) {
            return 'No aura symptoms reported';
        }

        $labels = collect($question['options'] ?? [])
            ->filter(function ($option) use ($values) {
                $value = $option['value'] ?? null;

                if ($value === 'none') {
                    return false;
                }

                return in_array($value, $values, true);
            })
            ->pluck('label')
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return null;
        }

        return 'Aura symptoms: ' . $labels->implode(', ');
    }
}
