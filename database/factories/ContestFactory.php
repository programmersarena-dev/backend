<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contest>
 */
class ContestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->boolean(10)
            ? $this->faker->dateTimeBetween('+1 day', '+7 days')
            : $this->faker->dateTimeBetween('-60 days', '-1 day');

        return [
            'type_id' => $this->faker->numberBetween(1, 4),
            'name' => rtrim($this->faker->sentence(3), '.'),
            'start_date' => $startDate,

            'duration_minutes' => $this->faker->randomElement([60, 120, 180, 240, 300]),

            'official' => $this->faker->boolean(40),
            'active' => true,
            'created_at' => $startDate,
            'updated_at' => $startDate,
        ];
    }
}
