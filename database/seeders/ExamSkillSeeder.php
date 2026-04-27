<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class ExamSkillSeeder extends Seeder
{
    public function run(): void
    {
        $exam = Exam::first();
        $listening = Skill::where('name', 'like', '%Listening%')->first();
        $reading = Skill::where('name', 'like', '%Reading%')->first();

        if ($exam && $listening && $reading) {
            $exam->skills()->syncWithoutDetaching([
                $listening->id => ['duration' => 30, 'order_index' => 1],
                $reading->id => ['duration' => 45, 'order_index' => 2]
            ]);
        }
    }
}
