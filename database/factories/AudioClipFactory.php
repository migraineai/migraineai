<?php

namespace Database\Factories;

use App\Models\AudioClip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AudioClip>
 */
class AudioClipFactory extends Factory
{
    protected $model = AudioClip::class;

    public function definition(): array
    {
        $duration = $this->faker->numberBetween(6, 15);

        return [
            'user_id' => User::factory(),
            'storage_path' => 'audio/' . Str::uuid() . '.webm',
            'duration_sec' => $duration,
            'codec' => 'webm-opus',
            'sample_rate' => 48_000,
            'status' => 'transcribed',
            'transcript_text' => $this->faker->paragraph(),
            'asr_provider' => 'whisper-1',
            'asr_confidence' => $this->faker->randomFloat(4, 0.82, 0.99),
        ];
    }

    public function uploaded(): static
    {
        return $this->state(fn () => [
            'status' => 'uploaded',
            'transcript_text' => null,
            'asr_provider' => null,
            'asr_confidence' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'asr_confidence' => null,
        ]);
    }
}
