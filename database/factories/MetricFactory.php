<?php

namespace Database\Factories;

use App\Enums\MetricType;
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
            'athlete_id'  => fake()->randomElement([1]),
            'date'        => fake()->dateTimeThisMonth()->format('Y-m-d'),
            'type'        => 'daily',
            'metric_type' => fake()->randomElement([MetricType::MORNING_GENERAL_FATIGUE->value, MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->value]),
            'value'       => fake()->numberBetween(1, 10),
        ];
    }
}
