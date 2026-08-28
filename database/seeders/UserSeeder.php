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
            [
                'user_type' => 'user',
                'handle' => 'nepes',
                'name' => 'Nepes',
                'email' => 'nepes@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
            ],
            [
                'user_type' => 'user',
                'handle' => 'sahy',
                'name' => 'Sahygeldi',
                'email' => 'sahy@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
            ],
            [
                'user_type' => 'user',
                'handle' => 'umyt',
                'name' => 'Umyt',
                'email' => 'umyt@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
            ],
            [
                'user_type' => 'user',
                'handle' => 'arslan',
                'name' => 'Arslan',
                'email' => 'arslan@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
            ],
            [
                'user_type' => 'user',
                'handle' => 'rahym',
                'name' => 'Rahym',
                'email' => 'rahym@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
            ],
            [
                'user_type' => 'user',
                'handle' => 'muhammetali',
                'name' => 'Muhammetali',
                'email' => 'muhammetali@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
            ],
            [
                'user_type' => 'user',
                'handle' => 'gurbangeldi',
                'name' => 'Gurbangeldi',
                'email' => 'gurbangeldi@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
            ],
            [
                'user_type' => 'user',
                'handle' => 'yhlas',
                'name' => 'Yhlas',
                'email' => 'yhlas@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
            ],
            [
                'user_type' => 'user',
                'handle' => 'sohbet',
                'name' => 'Sohbet',
                'email' => 'sohbet@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
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