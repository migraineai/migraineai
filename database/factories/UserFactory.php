<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cycleEnabled = fake()->boolean(20);
        $cycleLength = $cycleEnabled ? fake()->numberBetween(25, 32) : null;
        $periodLength = $cycleEnabled ? fake()->numberBetween(4, 7) : null;
        $lastPeriodStart = $cycleEnabled ? fake()->dateTimeBetween('-45 days', 'now') : null;

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'user',
            'gender' => fake()->randomElement(['male', 'female']),
            'age' => fake()->numberBetween(18, 75),
            'age_range' => fake()->randomElement(['18-24', '25-34', '35-44', '45-54', '55-64', '65+']),
            'time_zone' => fake()->randomElement(['America/New_York', 'Europe/London', 'Asia/Kolkata', 'Australia/Sydney']),
            'cycle_tracking_enabled' => $cycleEnabled,
            'cycle_length_days' => $cycleLength,
            'period_length_days' => $periodLength,
            'last_period_start_date' => $lastPeriodStart ? $lastPeriodStart->format('Y-m-d') : null,
            'daily_reminder_enabled' => fake()->boolean(85),
            'daily_reminder_hour' => fake()->numberBetween(7, 20),
            'post_attack_follow_up_hours' => fake()->randomElement([6, 12, 18, 24]),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withEpisodes(int $count = 3): static
    {
        return $this->afterCreating(function (User $user) use ($count): void {
            Episode::factory()
                ->count($count)
                ->for($user)
                ->create();
        });
    }
}
