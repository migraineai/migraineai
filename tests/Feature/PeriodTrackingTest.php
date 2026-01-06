<?php

namespace Tests\Feature;

use App\Models\PeriodLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeriodTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_period_tracking_page(): void
    {
        $user = User::factory()->create([
            'cycle_tracking_enabled' => true,
            'cycle_length_days' => 28,
            'period_length_days' => 5,
            'last_period_start_date' => Carbon::today()->subDays(14),
        ]);

        PeriodLog::factory()->create([
            'user_id' => $user->id,
            'logged_on' => Carbon::today()->toDateString(),
            'symptoms' => ['Cramps'],
            'severity' => 3,
            'is_period_day' => true,
        ]);

        $response = $this->actingAs($user)->get('/period-tracking');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('PeriodTracking'));
    }

    public function test_user_can_store_period_log(): void
    {
        $user = User::factory()->create([
            'cycle_tracking_enabled' => true,
            'cycle_length_days' => 28,
            'period_length_days' => 5,
            'last_period_start_date' => Carbon::today()->subDays(10),
        ]);

        $date = Carbon::today()->toDateString();

        $response = $this->actingAs($user)->post('/period-tracking/logs', [
            'date' => $date,
            'month' => Carbon::today()->format('Y-m'),
            'symptoms' => ['Cramps', 'Headache'],
            'severity' => 4,
            'notes' => 'Felt more fatigued than usual.',
            'is_period_day' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('period_logs', [
            'user_id' => $user->id,
            'logged_on' => $date,
            'is_period_day' => true,
            'severity' => 4,
        ]);
    }

    public function test_cannot_store_log_when_cycle_tracking_disabled(): void
    {
        $user = User::factory()->create([
            'cycle_tracking_enabled' => false,
        ]);

        $this->actingAs($user)
            ->post('/period-tracking/logs', [
                'date' => Carbon::today()->toDateString(),
                'month' => Carbon::today()->format('Y-m'),
                'symptoms' => ['Cramps'],
                'severity' => 3,
                'is_period_day' => true,
            ])
            ->assertForbidden();
    }
}
