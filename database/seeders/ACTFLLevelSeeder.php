<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class ACTFLLevelSeeder extends Seeder
{
    public function run(): void
    {
        $skills = Skill::all();
        
        $levels = [
            ['name' => 'Novice Low', 'min' => 0, 'max' => 100],
            ['name' => 'Novice Mid', 'min' => 101, 'max' => 200],
            ['name' => 'Novice High', 'min' => 201, 'max' => 300],
            ['name' => 'Intermediate Low', 'min' => 301, 'max' => 400],
            ['name' => 'Intermediate Mid', 'min' => 401, 'max' => 500],
            ['name' => 'Intermediate High', 'min' => 501, 'max' => 600],
            ['name' => 'Advanced Low', 'min' => 601, 'max' => 700],
            ['name' => 'Advanced Mid', 'min' => 701, 'max' => 800],
            ['name' => 'Advanced High', 'min' => 801, 'max' => 900],
        ];

        foreach ($skills as $skill) {
            foreach ($levels as $idx => $l) {
                Level::updateOrCreate(
                    ['skill_id' => $skill->id, 'level_number' => $idx + 1],
                    [
                        'name' => $l['name'],
                        'min_score' => $l['min'],
                        'max_score' => $l['max'],
                        'pass_threshold' => 70 // default 70% to pass to next level
                    ]
                );
            }
        }
    }
}
