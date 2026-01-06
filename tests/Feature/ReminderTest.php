<?php

namespace Tests\Feature;

use App\Jobs\ScheduleDailyReminders;
use App\Jobs\SendReminderNotification;
use App\Mail\PostAttackFollowUpMail;
use App\Models\Episode;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderScheduler;
use App\Services\TelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_daily_reminders_creates_single_pending_reminder_per_day(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'daily_reminder_enabled' => true,
            'daily_reminder_hour' => 8,
            'time_zone' => 'America/New_York',
        ]);

        $job = new ScheduleDailyReminders();

        $this->travelTo(Carbon::create(2025, 10, 26, 7, 0, 0, 'UTC'));

        $job->handle(app(ReminderScheduler::class));

        $this->assertDatabaseHas('reminders', [
            'user_id' => $user->id,
            'type' => Reminder::TYPE_DAILY,
            'status' => Reminder::STATUS_PENDING,
        ]);

        Queue::assertPushed(SendReminderNotification::class);

        $job->handle(app(ReminderScheduler::class));

        $this->assertSame(1, Reminder::where('user_id', $user->id)->where('type', Reminder::TYPE_DAILY)->count());
    }

    public function test_send_reminder_notification_marks_as_sent_and_records_telemetry(): void
    {
        $user = User::factory()->create();

        $reminder = Reminder::factory()->for($user)->create([
            'scheduled_for' => now()->subMinutes(5),
        ]);

        $job = new SendReminderNotification($reminder->id);

        $job->handle(app(TelemetryService::class));

        $reminder->refresh();

        $this->assertSame(Reminder::STATUS_SENT, $reminder->status);
        $this->assertNotNull($reminder->sent_at);
        $this->assertDatabaseHas('telemetry_events', [
            'user_id' => $user->id,
            'event_type' => 'reminder_fire',
        ]);
    }

    public function test_post_attack_follow_up_email_is_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $episode = Episode::factory()->for($user)->create();

        $reminder = Reminder::factory()
            ->for($user)
            ->postAttack()
            ->create([
                'scheduled_for' => now()->subMinutes(5),
                'payload' => ['episode_id' => $episode->id],
            ]);

        $job = new SendReminderNotification($reminder->id);

        $job->handle(app(TelemetryService::class));

        Mail::assertSent(PostAttackFollowUpMail::class, function (PostAttackFollowUpMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $reminder->refresh();

        $this->assertSame(Reminder::STATUS_SENT, $reminder->status);
    }
}
