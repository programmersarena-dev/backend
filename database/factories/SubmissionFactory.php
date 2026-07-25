<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Problem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement([
            'Accepted',
            'Wrong Answer',
            'Time Limit Exceeded',
            'Runtime Error',
            'Compilation Error'
        ]);

        return [
            // Safe relationship references that won't break if table sequences have gaps
            'user_id' => User::factory(),
            'problem_id' => Problem::factory(),

            'language' => $this->faker->randomElement(['gcc-10', 'g++-20', 'python-3.11', 'rust-1.75']),
            'status' => $status,

            'code' => <<<'CPP'
                #include <bits/stdc++.h>
                using namespace std;

                using ll = long long;
                #define all(x) begin(x), end(x)

                int main() {
                    ios_base::sync_with_stdio(0);
                    cin.tie(0);

                    int n;
                    if (cin >> n) {
                        cout << n << "\n";
                    }
                    return 0;
                }
                CPP,

            // Passed as a native array to cleanly trigger Eloquent's built-in array cast
            'outputs' => $status !== 'Compilation Error' && $status !== 'Queued' ? [
                [
                    'input' => '1',
                    'output' => $status === 'Accepted' ? '1' : '0',
                    'expected_output' => '1',
                    'log' => $status === 'Accepted' ? 'OK' : 'Wrong Answer',
                    'time' => $this->faker->numberBetween(5, 150),
                    'memory' => $this->faker->numberBetween(1024, 4096),
                ],
                [
                    'input' => '2',
                    'output' => $status === 'Accepted' ? '2' : '0',
                    'expected_output' => '2',
                    'log' => $status === 'Accepted' ? 'OK' : 'Wrong Answer',
                    'time' => $this->faker->numberBetween(5, 150),
                    'memory' => $this->faker->numberBetween(1024, 4096),
                ]
            ] : null,

            'output' => $status === 'Accepted' ? "1\n2\n" : "0\n0\n",
            'error_message' => $status === 'Compilation Error' ? 'error: expected ‘;’ before ‘return’' : null,

            'time' => $status !== 'Queued' ? $this->faker->numberBetween(10, 300) : null,
            'memory' => $status !== 'Queued' ? $this->faker->numberBetween(2048, 8192) : null,

            'judged_at' => $status !== 'Queued' ? $this->faker->dateTimeBetween('-1 month', 'now') : null,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
