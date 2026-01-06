<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDataExport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserDataExport>
 */
class UserDataExportFactory extends Factory
{
    protected $model = UserDataExport::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => UserDataExport::STATUS_READY,
            'disk' => 'local',
            'path' => 'exports/' . Str::uuid() . '.zip',
            'size_bytes' => $this->faker->numberBetween(1024, 2048),
            'download_token' => Str::random(40),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => UserDataExport::STATUS_PENDING,
            'path' => null,
            'download_token' => null,
            'expires_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => UserDataExport::STATUS_FAILED,
            'error_message' => 'Failed to prepare export',
        ]);
    }
}

