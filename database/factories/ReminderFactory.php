<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => Reminder::TYPE_DAILY,
            'scheduled_for' => now()->addHour(),
            'status' => Reminder::STATUS_PENDING,
            'channel' => 'email',
        ];
    }

    public function postAttack(): static
    {
        return $this->state(fn () => [
            'type' => Reminder::TYPE_POST_ATTACK,
        ]);
    }
}

