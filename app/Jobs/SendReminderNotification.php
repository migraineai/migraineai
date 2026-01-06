<?php

namespace App\Jobs;

use App\Mail\PostAttackFollowUpMail;
use App\Models\Episode;
use App\Models\Reminder;
use App\Services\TelemetryService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendReminderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private readonly int $reminderId)
    {
        $this->afterCommit = true;
    }

    public function handle(TelemetryService $telemetry): void
    {
        /** @var Reminder|null $reminder */
        $reminder = Reminder::query()->with('user')->find($this->reminderId);

        if (!$reminder || !$reminder->user) {
            return;
        }

        if ($reminder->status !== Reminder::STATUS_PENDING) {
            return;
        }

        if (Carbon::now()->lt($reminder->scheduled_for)) {
            $this->release($reminder->scheduled_for->diffInSeconds(Carbon::now()) + 60);
            return;
        }

        try {
            Log::channel('stack')->info('Dispatching reminder notification', [
                'reminder_id' => $reminder->id,
                'user_id' => $reminder->user_id,
                'type' => $reminder->type,
            ]);

            if ($reminder->channel === 'email' && $reminder->type === Reminder::TYPE_POST_ATTACK) {
                $this->sendPostAttackFollowUp($reminder);
            }

            $reminder->update([
                'status' => Reminder::STATUS_SENT,
                'sent_at' => now(),
            ]);

            $telemetry->record($reminder->user, 'reminder_fire', [
                'type' => $reminder->type,
                'reminder_id' => $reminder->id,
            ]);
        } catch (Throwable $exception) {
            $reminder->update([
                'status' => Reminder::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function sendPostAttackFollowUp(Reminder $reminder): void
    {
        if (empty($reminder->user->email)) {
            throw new RuntimeException('Cannot send reminder email without a destination address.');
        }

        $payload = is_array($reminder->payload) ? $reminder->payload : [];
        $episodeId = $payload['episode_id'] ?? null;

        $episode = null;

        if ($episodeId) {
            $episode = Episode::query()
                ->where('user_id', $reminder->user_id)
                ->find($episodeId);
        }

        Mail::to($reminder->user->email)->send(
            new PostAttackFollowUpMail($reminder->user, $reminder, $episode)
        );
    }
}
