<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\PeriodLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PeriodTrackingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $month = $this->resolveMonth($request->query('month'));
        $selectedDate = $this->resolveSelectedDate($request->query('date'), $month);

        $logsQuery = PeriodLog::query()
            ->where('user_id', $user->id);

        $episodes = Episode::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('start_time')
                    ->orWhere('start_time', '>=', Carbon::today()->subDays(120));
            })
            ->get();

        $monthRangeStart = $month->copy()->startOfMonth();
        $monthRangeEnd = $month->copy()->endOfMonth();

        $monthLogs = (clone $logsQuery)
            ->whereBetween('logged_on', [$monthRangeStart, $monthRangeEnd])
            ->get()
            ->keyBy(fn (PeriodLog $log) => $log->logged_on->toDateString());

        $recentLogs = (clone $logsQuery)
            ->orderByDesc('logged_on')
            ->limit(5)
            ->get();

        $calendar = $this->buildCalendar($user->cycle_length_days ?? 28, $user->period_length_days ?? 5, $user->last_period_start_date, $month, $monthLogs);

        if ($selectedDate) {
            if (!$calendar['days_map']->has($selectedDate->toDateString())) {
                $selectedDate = null;
            }
        }

        if (!$selectedDate) {
            $selectedDate = $this->defaultSelectedDate($calendar['days_map']);
        }

        $selectedDateString = $selectedDate?->toDateString();

        $periodCountdown = $this->buildPeriodCountdown($user->cycle_length_days ?? 28, $user->period_length_days ?? 5, $user->last_period_start_date);
        $cyclePhase = $this->buildCyclePhaseSummary($user->cycle_length_days ?? 28, $user->period_length_days ?? 5, $user->last_period_start_date);
        $phaseDurations = $this->buildPhaseDurations($episodes, $user);
        $phaseAnalysis = $this->buildEpisodePhaseAnalysis($episodes, $user);
        $cycleInsights = $this->buildInsights($user->id, $user->cycle_length_days ?? 28, $user->period_length_days ?? 5, $user->last_period_start_date);

        return Inertia::render('PeriodTracking', [
            'calendar' => [
                'month_label' => $month->isoFormat('MMMM YYYY'),
                'current_month' => $month->format('Y-m'),
                'previous_month' => $calendar['previous_month']?->format('Y-m'),
                'next_month' => $calendar['next_month']?->format('Y-m'),
                'weeks' => $calendar['weeks'],
                'selected_date' => $selectedDateString,
            ],
            'periodCountdown' => $periodCountdown,
            'cyclePhase' => $cyclePhase,
            'phaseDurations' => $phaseDurations,
            'phaseAnalysis' => $phaseAnalysis,
            'symptomOptions' => $this->symptomOptions(),
            'logs' => [
                'latest_logged_at' => optional($recentLogs->first()?->logged_on)?->isoFormat('MMM D, YYYY'),
                'recent' => $recentLogs->map(fn (PeriodLog $log) => [
                    'date' => $log->logged_on->isoFormat('MMMM D, YYYY'),
                    'iso_date' => $log->logged_on->toDateString(),
                    'symptoms' => $log->symptoms ?? [],
                    'severity' => $log->severity,
                    'notes' => $log->notes,
                    'is_period_day' => $log->is_period_day,
                ])->values(),
                'by_date' => $monthLogs->map(fn (PeriodLog $log) => [
                    'symptoms' => $log->symptoms ?? [],
                    'severity' => $log->severity,
                    'notes' => $log->notes,
                    'is_period_day' => $log->is_period_day,
                ])->all(),
            ],
            'insights' => $cycleInsights,
        ]);
    }

    private function resolveMonth(?string $month): Carbon
    {
        if ($month) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Exception) {
                // Ignore malformed input, fall through to default.
            }
        }

        return Carbon::today()->startOfMonth();
    }

    private function resolveSelectedDate(?string $date, Carbon $month): ?Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param Collection<string, PeriodLog> $monthLogs
     * @return array{weeks: array<int, array<int, array<string, mixed>>>, previous_month: ?Carbon, next_month: ?Carbon, days_map: Collection<string, array<string, mixed>>}
     */
    private function buildCalendar(int $cycleLength, int $periodLength, ?Carbon $lastPeriodStart, Carbon $month, Collection $monthLogs): array
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        $calendarStart = $startOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $endOfMonth->copy()->endOfWeek(Carbon::SATURDAY);

        $weeks = [];
        $daysMap = collect();
        $current = $calendarStart->copy();

        while ($current->lte($calendarEnd)) {
            $weekIndex = $current->diffInWeeks($calendarStart);
            $weeks[$weekIndex] ??= [];

            $dateString = $current->toDateString();
            $isInMonth = $current->month === $month->month;
            $log = $monthLogs->get($dateString);

            $status = $isInMonth ? $this->determineDayStatus($current, $cycleLength, $periodLength, $lastPeriodStart, $log) : 'empty';

            $dayData = [
                'label' => $isInMonth ? (string) $current->day : '',
                'status' => $status,
                'date' => $isInMonth ? $dateString : null,
                'is_today' => $current->isToday(),
                'has_log' => (bool) $log,
                'is_other_month' => !$isInMonth,
            ];

            $weeks[$weekIndex][] = $dayData;

            if ($isInMonth) {
                $daysMap->put($dateString, $dayData);
            }

            $current->addDay();
        }

        return [
            'weeks' => array_values($weeks),
            'previous_month' => $month->copy()->subMonthNoOverflow(),
            'next_month' => $month->copy()->addMonthNoOverflow(),
            'days_map' => $daysMap,
        ];
    }

    private function determineDayStatus(
        Carbon $date,
        int $cycleLength,
        int $periodLength,
        ?Carbon $lastPeriodStart,
        ?PeriodLog $log
    ): string {
        if ($log?->is_period_day) {
            return 'period';
        }

        if (!$lastPeriodStart) {
            return $log ? 'logged' : 'normal';
        }

        $cycleDay = $this->calculateCycleDay($date, $lastPeriodStart, $cycleLength);

        if ($cycleDay === null) {
            return $log ? 'logged' : 'normal';
        }

        if ($cycleDay >= 1 && $cycleDay <= $periodLength) {
            return 'period';
        }

        $ovulationDay = max(min($cycleLength - 14, $cycleLength), 1);
        $fertileStart = max($ovulationDay - 4, 1);
        $fertileEnd = min($ovulationDay + 1, $cycleLength);
        $predictedStart = max($cycleLength - max($periodLength + 1, 5), 1);

        if ($cycleDay === $ovulationDay) {
            return 'ovulation';
        }

        if ($cycleDay >= $fertileStart && $cycleDay <= $fertileEnd) {
            return 'fertile';
        }

        if ($cycleDay >= $predictedStart) {
            return 'predicted';
        }

        return $log ? 'logged' : 'normal';
    }

    private function calculateCycleDay(Carbon $date, Carbon $lastPeriodStart, int $cycleLength): ?int
    {
        if ($cycleLength <= 0) {
            return null;
        }

        $cycleStart = $lastPeriodStart->copy()->startOfDay();
        $difference = $cycleStart->diffInDays($date->copy()->startOfDay(), false);
        $mod = ($difference % $cycleLength + $cycleLength) % $cycleLength;

        return $mod + 1;
    }

    private function defaultSelectedDate(Collection $daysMap): ?Carbon
    {
        $today = Carbon::today()->toDateString();
        if ($daysMap->has($today)) {
            return Carbon::parse($today);
        }

        if ($firstWithLog = $daysMap->first(fn ($day) => $day['has_log'])) {
            return Carbon::parse($firstWithLog['date']);
        }

        if ($firstDay = $daysMap->first()) {
            return $firstDay['date'] ? Carbon::parse($firstDay['date']) : null;
        }

        return null;
    }

    private function buildPeriodCountdown(int $cycleLength, int $periodLength, ?Carbon $lastPeriodStart): array
    {
        if (!$lastPeriodStart) {
            return [
                'daysRemaining' => null,
                'cycleDay' => null,
                'expectedDate' => 'Update in settings',
            ];
        }

        $today = Carbon::today();
        $cycleDay = $this->calculateCycleDay($today, $lastPeriodStart, $cycleLength) ?? 1;

        $nextPeriod = $lastPeriodStart->copy();
        while ($nextPeriod->lte($today)) {
            $nextPeriod->addDays($cycleLength);
        }

        $daysRemaining = max($today->diffInDays($nextPeriod, false), 0);

        return [
            'daysRemaining' => $daysRemaining,
            'cycleDay' => $cycleDay,
            'expectedDate' => $nextPeriod->isoFormat('MMM D'),
        ];
    }

    private function buildCyclePhaseSummary(int $cycleLength, int $periodLength, ?Carbon $lastPeriodStart): array
    {
        $today = Carbon::today();

        $cycleDay = $lastPeriodStart ?
            $this->calculateCycleDay($today, $lastPeriodStart, $cycleLength) ?? 1 :
            null;

        $phase = $cycleDay !== null ? $this->phaseLabelForDay($cycleDay, $cycleLength, $periodLength) : 'Unknown';

        $risk = match ($phase) {
            'Ovulation' => 'High fertility',
            'Fertile Window' => 'Elevated fertility',
            default => 'Low fertility',
        };

        return [
            'phaseLabel' => $phase,
            'cycleDay' => $cycleDay,
            'totalDays' => $cycleLength,
            'risk' => $risk,
        ];
    }

    private function phaseLabelForDay(int $cycleDay, int $cycleLength, int $periodLength): string
    {
        if ($cycleDay <= $periodLength) {
            return 'Menstrual Phase';
        }

        $ovulationDay = max(min($cycleLength - 14, $cycleLength), 1);
        $fertileStart = max($ovulationDay - 4, 1);
        $fertileEnd = min($ovulationDay + 1, $cycleLength);

        if ($cycleDay >= $fertileStart && $cycleDay < $ovulationDay) {
            return 'Fertile Window';
        }

        if ($cycleDay === $ovulationDay) {
            return 'Ovulation';
        }

        if ($cycleDay > $ovulationDay && $cycleDay <= $fertileEnd) {
            return 'Luteal (post-ovulation)';
        }

        return $cycleDay > $fertileEnd && $cycleDay < $cycleLength ? 'Luteal Phase' : 'Late Luteal';
    }

    private function buildEpisodePhaseAnalysis(Collection $episodes, $user): array
    {
        if (!$user || !$user->cycle_tracking_enabled || !$user->last_period_start_date) {
            return [];
        }

        $cycleLength = max($user->cycle_length_days ?? 28, 1);
        $periodLength = max($user->period_length_days ?? 5, 1);

        $phaseCounts = [
            'Period Days' => 0,
            'Fertile window' => 0,
            'Ovulation' => 0,
            'Projected Cycle' => 0,
        ];

        foreach ($episodes as $episode) {
            $reference = $this->episodeReferenceDate($episode);

            if (!$reference) {
                continue;
            }

            $cycleDay = $this->calculateCycleDay($reference, $user->last_period_start_date, $cycleLength);
            if ($cycleDay === null) {
                continue;
            }

            $ovulationDay = ceil($cycleLength / 2);
            $fertileStart = max(1, $ovulationDay - 5);
            $fertileEnd = min($cycleLength, $ovulationDay + 1);

            if ($cycleDay <= $periodLength) {
                $phaseCounts['Period Days']++;
            } elseif ($cycleDay >= $fertileStart && $cycleDay < $ovulationDay) {
                $phaseCounts['Fertile window']++;
            } elseif ($cycleDay === $ovulationDay) {
                $phaseCounts['Ovulation']++;
            } else {
                $phaseCounts['Projected Cycle']++;
            }
        }

        $total = array_sum($phaseCounts);

        $colors = [
            'Period Days' => '#EF4444',
            'Fertile window' => '#10B981',
            'Ovulation' => '#2f3144',
            'Projected Cycle' => '#7d7f8d',
        ];

        return collect($phaseCounts)
            ->map(function ($count, $phase) use ($total, $colors) {
                return [
                    'label' => $phase,
                    'count' => $count,
                    'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                    'color' => $colors[$phase] ?? '#6B7280',
                ];
            })
            ->values()
            ->all();
    }

    private function buildPhaseDurations(Collection $episodes, $user): array
    {
        if (!$user || !$user->cycle_tracking_enabled || !$user->last_period_start_date) {
            return [];
        }

        $cycleLength = max($user->cycle_length_days ?? 28, 1);
        $periodLength = max($user->period_length_days ?? 5, 1);

        if ($cycleLength <= 0) {
            return [];
        }

        $buckets = [];

        foreach ($episodes as $episode) {
            $reference = $this->episodeReferenceDate($episode);
            $duration = $this->episodeDurationMinutes($episode);

            if (!$reference || $duration === null) {
                continue;
            }

            $cycleDay = $this->calculateCycleDay($reference, $user->last_period_start_date, $cycleLength);
            if ($cycleDay === null) {
                continue;
            }

            $label = $this->phaseLabelForDay($cycleDay, $cycleLength, $periodLength);

            if (!isset($buckets[$label])) {
                $buckets[$label] = [
                    'phase' => $label,
                    'days' => [],
                    'total_minutes' => 0,
                    'count' => 0,
                ];
            }

            $buckets[$label]['days'][] = $cycleDay;
            $buckets[$label]['total_minutes'] += $duration;
            $buckets[$label]['count'] += 1;
        }

        return collect($buckets)
            ->map(function (array $bucket) {
                sort($bucket['days']);
                $minDay = $bucket['days'][0] ?? null;
                $maxDay = $bucket['days'][count($bucket['days']) - 1] ?? null;

                return [
                    'phase' => $bucket['phase'],
                    'range' => $minDay && $maxDay ? ($minDay === $maxDay ? "Day {$minDay}" : "Days {$minDay}-{$maxDay}") : '—',
                    'average_minutes' => $bucket['count'] > 0 ? round($bucket['total_minutes'] / $bucket['count'], 1) : null,
                    'count' => $bucket['count'],
                ];
            })
            ->values()
            ->all();
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

    private function buildInsights(int $userId, int $cycleLength, int $periodLength, ?Carbon $lastPeriodStart): array
    {
        $logs = PeriodLog::query()
            ->where('user_id', $userId)
            ->orderByDesc('logged_on')
            ->limit(30)
            ->get();

        $phaseSummary = $logs
            ->groupBy(function (PeriodLog $log) use ($cycleLength, $periodLength, $lastPeriodStart) {
                if (!$lastPeriodStart) {
                    return 'Unclassified';
                }
                $cycleDay = $this->calculateCycleDay($log->logged_on, $lastPeriodStart, $cycleLength) ?? 1;
                return $this->phaseLabelForDay($cycleDay, $cycleLength, $periodLength);
            })
            ->map->count();

        $migraineWindowStart = Carbon::today()->copy()->subDays($cycleLength);
        $migraineEpisodes = Episode::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($migraineWindowStart) {
                $query->whereNull('start_time')
                    ->orWhere('start_time', '>=', $migraineWindowStart);
            })
            ->count();

        return [
            'total_logs' => $logs->count(),
            'phase_summary' => $phaseSummary->all(),
            'migraine_episodes_recent' => $migraineEpisodes,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function symptomOptions(): array
    {
        return [
            'Cramps',
            'Bloating',
            'Mood Swings',
            'Fatigue',
            'Breast Tenderness',
            'Headache',
            'Sleep Changes',
            'Acne',
            'Back Pain',
            'Food Cravings',
        ];
    }
}
