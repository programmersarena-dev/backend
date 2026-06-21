<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Standing>
 */
class StandingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        // if ($contestType === 'Classic') {
        //     \App\Models\Standing::create([
        //         'username' => $countUsers->firstWhere('id',rand(1,$countUsers)),
        //         'problems' => array_fill(0, $countProblems, array_fill(0, 1, 0)),
        //         'total_score' => 0,
        //     ]);
        // } elseif ($contestType === 'Duel') {
        //     \App\Models\Standing::create([
        //         'username1' => $countUsers->firstWhere('id',rand(1,$countUsers)),
        //         'problems1' => array_fill(0, $countProblems, array_fill(0, 1, 0)),
        //         'total_score1' => 0,
        //         'username2' => $countUsers->firstWhere('id',rand(1,$countUsers)),
        //         'problems2' => array_fill(0, $countProblems, array_fill(0, 1, 0)),
        //         'total_score2' => 0,
        //     ]);
        // }

        return [
            'contest_id'   => null,
            'result' => '[]',
        ];
    }
}
