<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ReminderScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScheduleDailyReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(ReminderScheduler $scheduler): void
    {
        User::query()
            ->where('daily_reminder_enabled', true)
            ->chunkById(100, function ($users) use ($scheduler) {
                /** @var User $user */
                foreach ($users as $user) {
                    $scheduledFor = $this->resolveScheduleTime($user);

                    $reminder = $scheduler->scheduleDailyReminder($user, $scheduledFor);

                    Log::info('Daily reminder queued', [
                        'reminder_id' => $reminder->id,
                        'user_id' => $user->id,
                        'scheduled_for' => $scheduledFor->toIso8601String(),
                    ]);
                }
            });
    }

    private function resolveScheduleTime(User $user): CarbonImmutable
    {
        $timeZone = $user->time_zone ?: 'UTC';
        $hour = (int)($user->daily_reminder_hour ?? 9);

        $local = CarbonImmutable::now($timeZone)
            ->startOfDay()
            ->addHours($hour);

        if ($local->isPast()) {
            $local = $local->addDay();
        }

        return $local->setTimezone('UTC');
    }
}
