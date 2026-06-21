<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            \App\Models\User::factory(300)->create()->each(function ($user) {
                \App\Models\Profile::factory()->create(['user_id' => $user->id]);
            });
            \App\Models\Blog::factory(100)->create();
            \App\Models\Contest::factory(100)->create()->each(function ($contest) {
                $test_cases = $contest->type() == 'IOI' ? 'test_cases/sample_subtasks' : 'test_cases/sample';
                \App\Models\Problem::factory(rand(4, 6))->create(['contest_id' => $contest->id, 'test_cases' => $test_cases]);
                \App\Models\Standing::factory()->create(['contest_id' => $contest->id]);
            });
            \App\Models\Submission::factory(500)->create();
        }
    }
}
