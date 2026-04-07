<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = ['Listening', 'Reading Comprehension', 'Structure', 'Writing', 'Speaking'];

        foreach ($skills as $skill) {
            Skill::firstOrCreate(['name' => $skill]);
        }
    }
}
