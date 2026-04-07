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

        foreach ($skills as $skill) {
            for ($i = 1; $i <= 9; $i++) {
                $min = ($i - 1) * 100 + ($i > 1 ? 1 : 0);
                $max = $i * 100;

                Level::firstOrCreate([
                    'skill_id' => $skill->id,
                    'level_number' => $i,
                ], [
                    'min_score' => $min,
                    'max_score' => $max,
                ]);
            }
        }
    }
}
