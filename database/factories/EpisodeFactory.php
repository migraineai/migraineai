<?php

namespace Database\Factories;

use App\Models\AudioClip;
use App\Models\Episode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    protected $model = Episode::class;

    public function definition(): array
    {
        $userFactory = User::factory();

        $start = Carbon::now()->subDays($this->faker->numberBetween(0, 30))->setTime(
            $this->faker->numberBetween(6, 22),
            $this->faker->numberBetween(0, 59)
        );
        $durationMinutes = $this->faker->numberBetween(30, 6 * 60);

        return [
            'user_id' => $userFactory,
            'audio_clip_id' => $this->faker->boolean(70)
                ? AudioClip::factory()
                    ->for($userFactory, 'user')
                : null,
            'start_time' => $start,
            'end_time' => (clone $start)->addMinutes($durationMinutes),
            'intensity' => $this->faker->numberBetween(1, 10),
            'pain_location' => $this->faker->randomElement(['left', 'right', 'bilateral', 'frontal', 'occipital']),
            'aura' => $this->faker->boolean(35),
            'symptoms' => $this->faker->randomElements(
                ['nausea', 'light sensitivity', 'sound sensitivity', 'visual aura', 'dizziness'],
                $this->faker->numberBetween(1, 3)
            ),
            'triggers' => $this->faker->randomElements(
                ['stress', 'sleep disruption', 'dehydration', 'screen time', 'weather changes', 'hormonal'],
                $this->faker->numberBetween(1, 2)
            ),
            'what_you_tried' => $this->faker->sentence(),
            'notes' => $this->faker->paragraph(),
            'extraction_confidences' => [
                'intensity' => $this->faker->randomFloat(2, 0.6, 0.99),
                'aura' => $this->faker->randomFloat(2, 0.6, 0.99),
            ],
        ];
    }
}
