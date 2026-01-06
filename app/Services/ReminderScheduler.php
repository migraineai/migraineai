<?php

namespace App\Services;

use App\Jobs\SendReminderNotification;
use App\Models\Episode;
use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReminderScheduler
{
    public function __construct(private readonly TelemetryService $telemetry)
    {
    }

    public function scheduleDailyReminder(User $user, CarbonImmutable $scheduledFor): Reminder
    {
        return DB::transaction(function () use ($user, $scheduledFor) {
            $existing = $user->reminders()
                ->where('type', Reminder::TYPE_DAILY)
                ->whereDate('scheduled_for', $scheduledFor->toDateString())
                ->where('status', Reminder::STATUS_PENDING)
                ->first();

            if ($existing) {
                return $existing;
            }

            $reminder = $user->reminders()->create([
                'type' => Reminder::TYPE_DAILY,
                'scheduled_for' => $scheduledFor,
                'payload' => [
                    'hour' => $scheduledFor->isoFormat('HH:mm'),
                ],
            ]);

            $this->dispatchSendJob($reminder);

            return $reminder;
        });
    }

    public function schedulePostAttackFollowUp(User $user, Episode $episode): Reminder
    {
        $offsetHours = max(1, (int)($user->post_attack_follow_up_hours ?? 12));

        $scheduledFor = $episode->end_time
            ? CarbonImmutable::instance($episode->end_time)->addHours($offsetHours)
            : CarbonImmutable::now()->addHours($offsetHours);

        return DB::transaction(function () use ($user, $scheduledFor, $episode) {
            $reminder = $user->reminders()->create([
                'type' => Reminder::TYPE_POST_ATTACK,
                'scheduled_for' => $scheduledFor,
                'payload' => [
                    'episode_id' => $episode->id,
                ],
            ]);

            $this->dispatchSendJob($reminder);

            return $reminder;
        });
    }

    private function dispatchSendJob(Reminder $reminder): void
    {
        $delaySeconds = $reminder->scheduled_for->isFuture()
            ? Carbon::now()->diffInSeconds($reminder->scheduled_for)
            : 0;

        SendReminderNotification::dispatch($reminder->id)->delay($delaySeconds);
    }
}
