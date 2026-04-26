<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('exams')->truncate();
        DB::table('exam_skill')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $category = ExamCategory::where('slug', 'adult')->first();

        $exam = Exam::create([
            'id' => 1,
            'exam_category_id' => $category ? $category->id : null,
            'title' => 'Main Proficiency Exam',
            'description' => 'Standard adaptive exam covering all 5 core skills.',
            'is_active' => true,
            'timer_type' => 'global',
            'time_limit' => 120, // 2 hours
            'is_default' => true,
            'passing_score' => 70,
            'default_want_reading' => 1,
            'default_want_listening' => 1,
            'default_want_grammar' => 1,
            'default_want_writing' => 1,
            'default_want_speaking' => 1,
        ]);

        // Link all 5 skills to this exam
        $skills = Skill::all();
        foreach ($skills as $index => $skill) {
            DB::table('exam_skill')->insert([
                'exam_id' => $exam->id,
                'skill_id' => $skill->id,
                'order_index' => $index,
                'duration' => 20, // 20 mins per skill
            ]);
        }
    }
}
