<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\User;
use App\Models\ContestStanding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContestStanding>
 */
class ContestStandingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ContestStanding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $problemCount = 5;
        $userCount = fake()->numberBetween(100, 200);

        $users = User::inRandomOrder()->take($userCount)->pluck('handle')->toArray();
        if (empty($users)) {
            $users = array_map(fn () => fake()->userName(), range(1, $userCount));
        }

        $result = [];

        foreach ($users as $handle) {
            $problems = [];
            $totalScore = 0;

            for ($i = 0; $i < $problemCount; $i++) {
                $status = fake()->randomElement(['AC', 'WA', 'TLE', 'MLE', 'CE']);
                
                if ($status === 'AC') {
                    $score = fake()->numberBetween(100, 1000);
                } else {
                    $score = 0;
                }

                $totalScore += $score;

                $problems[] = [
                    'score'  => $score,
                    'status' => $status,
                ];
            }

            $result[] = [
                'handle'      => $handle,
                'problems'    => $problems,
                'total_score' => $totalScore,
            ];
        }

        usort($result, fn ($a, $b) => $b['total_score'] <=> $a['total_score']);

        return [
            'contest_id' => null,
            'result'     => $result,
        ];
    }

    public function asArray(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'result' => json_decode($attributes['result'], true),
            ];
        });
    }
}