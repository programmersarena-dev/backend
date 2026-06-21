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
        $start_date = $this->faker->boolean(1)
            ? $this->faker->dateTimeBetween('+1 day', '+5 days')
            : $this->faker->dateTimeBetween('-500 days', '-1 day');
        return [
            'type_id' => $this->faker->numberBetween(1, 4),
            'name' => $this->faker->sentence(3),
            'authorIds' => '[]',
            'start_date' => $start_date,
            'duration' => sprintf('%02d:00', $this->faker->numberBetween(1, 5)),
            'participantIds' => '{"official":[],"unofficial":[]}',
            'official' => $this->faker->boolean(50),
            'active' => true,
            'created_at' => $start_date,
            'updated_at' => $start_date,
        ];
    }
}
