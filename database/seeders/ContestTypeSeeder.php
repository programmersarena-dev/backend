<?php

namespace Database\Seeders;

use App\Models\ContestType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ContestTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Classic'],
            ['name' => 'IOI'],
            ['name' => 'ICPC'],
            ['name' => 'Duel'],
        ];

        foreach ($types as $type) {
            ContestType::firstOrCreate($type);
        }
    }
}
