<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Problem>
 */
class ProblemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contest_id'   => null,
            'name'         => $this->faker->words(3, true),
            'tags'         => json_encode($this->faker->randomElements(['dp', 'graph', 'math', 'greedy'], rand(1, 3))),
            'time_limit'   => rand(1,5),
            'memory_limit' => 1024,
            'score'        => rand(8, 35) * 100,
            'description'  => $this->faker->paragraph(),
            'input'        => $this->faker->sentence(),
            'output'       => $this->faker->sentence(),
            'test_cases'   => 'test_cases/sample',
            'note'         => '',
        ];
    }
}
