<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\SendReminderNotification;
use App\Models\Episode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class EpisodeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
        Session::start();
    }

    public function test_episode_index_returns_period_days_when_cycle_enabled(): void
    {
        $user = User::factory()->create([
            'cycle_tracking_enabled' => true,
            'cycle_length_days' => 28,
            'period_length_days' => 5,
            'last_period_start_date' => Carbon::create(2025, 10, 1),
        ]);

        Episode::factory()->for($user)->create([
            'start_time' => Carbon::create(2025, 10, 15, 8, 0, 0),
            'end_time' => Carbon::create(2025, 10, 15, 10, 0, 0),
        ]);

        $response = $this->actingAs($user)->getJson('/episodes?range=30');

        $response->assertOk();

        $body = $response->json();

        $this->assertSame(1, $body['summary']['total_episodes']);
        $this->assertContains('2025-10-01', $body['period_days']);
        $this->assertContains('2025-10-02', $body['period_days']);
        $this->assertNotContains('2025-10-20', $body['period_days']);
    }

    public function test_episode_index_defaults_to_thirty_day_range(): void
    {
        $user = User::factory()->create();

        Episode::factory()->for($user)->create([
            'start_time' => Carbon::now()->subDays(45),
            'end_time' => Carbon::now()->subDays(45)->addHour(),
        ]);

        $response = $this->actingAs($user)->getJson('/episodes?range=999');

        $response->assertOk();

        $episodes = $response->json('episodes');
        $this->assertCount(0, $episodes);
    }

    public function test_episode_store_creates_follow_up_reminder_and_telemetry(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'post_attack_follow_up_hours' => 6,
        ]);

        $payload = [
            'start_time' => Carbon::now()->subHour()->toIso8601String(),
            'end_time' => Carbon::now()->toIso8601String(),
            'intensity' => 7,
            'pain_location' => 'left',
        ];

        $response = $this->actingAs($user)->postJson('/episodes', $payload + ['_token' => Session::token()]);

        $response->assertCreated();

        $this->assertDatabaseHas('reminders', [
            'user_id' => $user->id,
            'type' => 'post_attack_follow_up',
        ]);

        Queue::assertPushed(SendReminderNotification::class);

        $this->assertDatabaseHas('telemetry_events', [
            'user_id' => $user->id,
            'event_type' => 'log_save',
        ]);
    }
}
