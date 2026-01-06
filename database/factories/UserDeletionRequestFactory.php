<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDeletionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDeletionRequest>
 */
class UserDeletionRequestFactory extends Factory
{
    protected $model = UserDeletionRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => UserDeletionRequest::STATUS_PENDING,
            'scheduled_for' => null,
            'processed_at' => null,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => UserDeletionRequest::STATUS_SCHEDULED,
            'scheduled_for' => now()->addDays(3),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => UserDeletionRequest::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);
    }
}

