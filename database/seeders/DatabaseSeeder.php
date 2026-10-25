<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CountrySeeder::class,
            ContestTypeSeeder::class,
        ]);

        if (app()->environment('local')) {
            \App\Models\User::factory(500)->create()->each(function ($user) {
                \App\Models\Profile::factory()->create(['user_id' => $user->id]);
            });
            \App\Models\Blog::factory(100)->create();
            $contests = \App\Models\Contest::factory(50)->create();

            $contests->each(function ($contest) {
                $testCasesPath = $contest->hasAttachments()
                    ? 'test_cases/sample_subtasks'
                    : 'test_cases/sample';

                \App\Models\Problem::factory(5)->create([
                    'contest_id' => $contest->id,
                    'test_cases_path' => $testCasesPath
                ]);

                \App\Models\ContestStanding::factory()->create([
                    'contest_id' => $contest->id
                ]);
            });
            \App\Models\Submission::factory(500)->create();
        }
    }
}
