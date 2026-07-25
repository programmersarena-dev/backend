<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'user_type' => 'admin',
                'handle' => 'admin',
                'name' => 'Admin',
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
                'email_verified_at' => now(),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            ],
            [
                'user_type' => 'judge-daemon',
                'handle' => 'judge_daemon',
                'name' => 'Judge Daemon',
                'email' => 'judge@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('secret_judge_key'),
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            Profile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $user->name,
                    'last_name' => '',
                    'image' => null,
                ]
            );
        }
    }
}