<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            ['name' => 'Listening', 'short_code' => 'LIST'],
            ['name' => 'Reading',   'short_code' => 'READ'],
            ['name' => 'Structure', 'short_code' => 'GRAM'],
            ['name' => 'Writing',   'short_code' => 'WRIT'],
            ['name' => 'Speaking',  'short_code' => 'SPEK'],
        ];

        foreach ($skills as $skill) {
            Skill::updateOrCreate(
                ['name' => $skill['name']],
                ['short_code' => $skill['short_code']]
            );
        }
    }
}
