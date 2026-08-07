<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\Problem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Problem>
 */
class ProblemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Problem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(rand(2, 4), true);

        return [
            'contest_id' => Contest::factory(),
            'code' => strtoupper(fake()->unique()->bothify('???-###')),
            'slug' => Str::slug($name),
            'name' => ucfirst($name),

            'tags' => fake()->randomElements([
                'dp', 'graphs', 'math', 'greedy', 'strings', 'data structures', 'trees', 'binary search'
            ], rand(1, 3)),

            'time_limit' => fake()->randomElement([500, 1000, 1500, 2000, 3000]),
            'memory_limit' => fake()->randomElement([128, 256, 512]),

            'difficulty' => fake()->numberBetween(8, 35) * 100,
            'score' => 100,

            'description' => fake()->paragraphs(3, true),
            'input' => fake()->paragraph(),
            'output' => fake()->paragraph(),
            'note' => fake()->boolean(40) ? fake()->sentence() : null,

            'test_cases_path' => 'test_cases/sample',
            'is_public' => true,
        ];
    }

    /**
     * State for standalone problems outside any contest.
     */
    public function standalone(): static
    {
        return $this->state(fn (array $attributes) => [
            'contest_id' => null,
        ]);
    }
}
