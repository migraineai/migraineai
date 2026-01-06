<?php

namespace Database\Factories;

use App\Models\PeriodLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodLog>
 */
class PeriodLogFactory extends Factory
{
    protected $model = PeriodLog::class;

    public function definition(): array
    {
        $isPeriodDay = $this->faker->boolean(60);

        return [
            'user_id' => User::factory(),
            'logged_on' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'is_period_day' => $isPeriodDay,
            'severity' => $this->faker->numberBetween(1, 5),
            'symptoms' => $this->faker->randomElements([
                'Cramps',
                'Bloating',
                'Mood Swings',
                'Fatigue',
                'Breast Tenderness',
                'Headache',
                'Sleep Changes',
                'Acne',
            ], $this->faker->numberBetween(1, 4)),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
