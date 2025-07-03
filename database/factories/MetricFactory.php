<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Metric>
 */
class MetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'athlete_id' => fake()->randomElement([1, 2]),
            'date' => fake()->dateTimeThisMonth()->format('Y-m-d'),
            'type' => 'daily',
            'metric_type' => fake()->randomElement(['morning_hrv', 'post_session_subjective_fatigue']),
            'value' => fake()->numberBetween(1, 10),
        ];
    }
}
