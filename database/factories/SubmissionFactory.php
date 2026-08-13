<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Problem;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement([
            'AC',
            'WA',
            'TLE',
            'MLE',
            'RE',
            'CE',
        ]);

        $isAc = $status === 'AC';
        $isCe = $status === 'CE';

        return [
            'user_id' => User::inRandomOrder()->take(1)->first()->id,
            'problem_id' => Problem::inRandomOrder()->take(1)->first()->id,
            'contest_id' => function (array $attributes) {
                return Problem::find($attributes['problem_id'])?->contest_id;
            },

            'language' => $this->faker->randomElement(['gcc-10']),
            'status' => $status,

            'code' => <<<'CPP'
                #include <bits/stdc++.h>
                using namespace std;

                int main() {
                    int n;
                    if (cin >> n) {
                        cout << n << "\n";
                    }
                    return 0;
                }
                CPP,

            // Subtask structure matching competition judging engines (e.g. IOI / CP)
            'outputs' => !$isCe ? [
                [
                    'index' => 0,
                    'points' => $isAc ? 100 : 0,
                    'tests' => [
                        [
                            'status' => $isAc ? 'OK' : $status,
                            'input' => '1',
                            'output' => $isAc ? '1' : '0',
                            'expected_output' => '1',
                            'time_used_ms' => $this->faker->numberBetween(5, 150),
                            'memory_used_kb' => $this->faker->numberBetween(1024, 4096),
                            'log' => $isAc ? 'OK' : ($status === 'WA' ? 'Wrong Answer' : $status),
                        ],
                        [
                            'status' => $isAc ? 'OK' : $status,
                            'input' => '2',
                            'output' => $isAc ? '2' : '0',
                            'expected_output' => '2',
                            'time_used_ms' => $this->faker->numberBetween(5, 150),
                            'memory_used_kb' => $this->faker->numberBetween(1024, 4096),
                            'log' => $isAc ? 'OK' : ($status === 'WA' ? 'Wrong Answer' : $status),
                        ],
                    ],
                ],
            ] : null,

            'output' => $isAc ? "1\n2\n" : "0\n0\n",
            'error_message' => $isCe ? 'error: expected ‘;’ before ‘return’' : null,

            'time' => !$isCe ? $this->faker->numberBetween(10, 300) : null,
            'memory' => !$isCe ? $this->faker->numberBetween(2048, 8192) : null,

            'judged_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}