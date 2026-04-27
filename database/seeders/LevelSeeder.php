<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $skills = Skill::all();

        $levelNames = [
            1 => 'Novice Low',
            2 => 'Novice Mid',
            3 => 'Novice High',
            4 => 'Intermediate Low',
            5 => 'Intermediate Mid',
            6 => 'Intermediate High',
            7 => 'Advanced Low',
            8 => 'Advanced Mid',
            9 => 'Advanced High',
        ];

        foreach ($skills as $skill) {
            for ($i = 1; $i <= 9; $i++) {
                $min = ($i - 1) * 100 + ($i > 1 ? 1 : 0);
                $max = $i * 100;

                Level::updateOrCreate([
                    'skill_id' => $skill->id,
                    'level_number' => $i,
                ], [
                    'name' => $levelNames[$i],
                    'min_score' => $min,
                    'max_score' => $max,
                    'is_active' => true,
                ]);
            }
        }
    }
}
