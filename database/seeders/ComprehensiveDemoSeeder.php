<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Skill;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Exam;
use App\Models\ExamCategory;
use Illuminate\Support\Str;

class ComprehensiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Skills Exist
        $skills = [
            'Reading' => Skill::updateOrCreate(['name' => 'Reading'], ['short_code' => 'R']),
            'Listening' => Skill::updateOrCreate(['name' => 'Listening'], ['short_code' => 'L']),
            'Structure' => Skill::updateOrCreate(['name' => 'Structure'], ['short_code' => 'G']),
            'Writing' => Skill::updateOrCreate(['name' => 'Writing'], ['short_code' => 'W']),
            'Speaking' => Skill::updateOrCreate(['name' => 'Speaking'], ['short_code' => 'S']),
        ];

        // 2. Clear previous demo data if any (Optional - using distinct tags)
        Question::where('group_tag', 'DEMO_DATA')->delete();

        // --- READING: PASSAGE GROUP ---
        $passage1Content = "The Industrial Revolution was a period of global economic transition towards more efficient and stable manufacturing processes that succeeded the Agricultural Revolution. This transition included going from hand production methods to machines, new chemical manufacturing and iron production processes, the increasing use of steam power and water power, the development of machine tools and the rise of the mechanized factory system.";
        
        $p1GroupId = Str::uuid()->toString();
        $readingQuestions = [
            [
                'content' => 'What was the primary shift described in the text?',
                'options' => [
                    ['text' => 'From machine production to hand production', 'correct' => false],
                    ['text' => 'From hand production to mechanized factories', 'correct' => true],
                    ['text' => 'From steam power to water power', 'correct' => false],
                ]
            ],
            [
                'content' => 'Which revolution preceded the Industrial Revolution according to the text?',
                'options' => [
                    ['text' => 'The French Revolution', 'correct' => false],
                    ['text' => 'The Agricultural Revolution', 'correct' => true],
                    ['text' => 'The Digital Revolution', 'correct' => false],
                ]
            ],
            [
                'content' => 'The text mentions the increasing use of which power source?',
                'options' => [
                    ['text' => 'Solar power', 'correct' => false],
                    ['text' => 'Steam power', 'correct' => true],
                    ['text' => 'Nuclear power', 'correct' => false],
                ]
            ]
        ];

        foreach ($readingQuestions as $qData) {
            $q = Question::create([
                'skill_id' => $skills['Reading']->id,
                'type' => 'mcq',
                'content' => $qData['content'],
                'passage_content' => $passage1Content,
                'passage_group_id' => $p1GroupId,
                'passage_limit' => 2, // Show only 2 out of 3
                'difficulty_level' => 1,
                'points' => 5,
                'group_tag' => 'DEMO_DATA'
            ]);
            foreach ($qData['options'] as $opt) {
                $q->options()->create(['option_text' => $opt['text'], 'is_correct' => $opt['correct']]);
            }
        }

        // --- LISTENING: AUDIO QUESTIONS ---
        $listeningTasks = [
            [
                'prompt' => 'Listen carefully. What is the speaker asking for?',
                'audio' => 'questions/demo_audio_1.mp3',
                'options' => [
                    ['text' => 'A cup of coffee', 'correct' => true],
                    ['text' => 'A map of the city', 'correct' => false],
                    ['text' => 'A train ticket', 'correct' => false],
                ]
            ],
            [
                'prompt' => 'Identify the emotion in the speaker\'s voice.',
                'audio' => 'questions/demo_audio_2.mp3',
                'options' => [
                    ['text' => 'Excitement', 'correct' => true],
                    ['text' => 'Boredom', 'correct' => false],
                    ['text' => 'Anger', 'correct' => false],
                ]
            ]
        ];

        foreach ($listeningTasks as $lt) {
            $q = Question::create([
                'skill_id' => $skills['Listening']->id,
                'type' => 'mcq',
                'content' => $lt['prompt'],
                'media_path' => $lt['audio'],
                'difficulty_level' => 1,
                'points' => 10,
                'group_tag' => 'DEMO_DATA'
            ]);
            foreach ($lt['options'] as $opt) {
                $q->options()->create(['option_text' => $opt['text'], 'is_correct' => $opt['correct']]);
            }
        }

        // --- STRUCTURE: MULTI-LEVEL MCQs ---
        $structureData = [
            ['content' => 'He ___ to the gym every morning.', 'options' => [['text' => 'go', 'correct' => false], ['text' => 'goes', 'correct' => true]], 'level' => 1],
            ['content' => 'If it ___ tomorrow, we will cancel the picnic.', 'options' => [['text' => 'rains', 'correct' => true], ['text' => 'will rain', 'correct' => false]], 'level' => 2],
            ['content' => 'Hardly ___ the station when the train left.', 'options' => [['text' => 'had I reached', 'correct' => true], ['text' => 'I reached', 'correct' => false]], 'level' => 3],
        ];

        foreach ($structureData as $sd) {
            $q = Question::create([
                'skill_id' => $skills['Structure']->id,
                'type' => 'mcq',
                'content' => $sd['content'],
                'difficulty_level' => $sd['level'],
                'points' => 2,
                'group_tag' => 'DEMO_DATA'
            ]);
            foreach ($sd['options'] as $opt) {
                $q->options()->create(['option_text' => $opt['text'], 'is_correct' => $opt['correct']]);
            }
        }

        // --- WRITING: TASKS WITH LIMITS ---
        Question::create([
            'skill_id' => $skills['Writing']->id,
            'type' => 'writing',
            'content' => 'Write a formal report (approx. 150 words) describing the impact of remote work on employee productivity based on your observations.',
            'difficulty_level' => 2,
            'points' => 20,
            'min_words' => 100,
            'max_words' => 250,
            'group_tag' => 'DEMO_DATA'
        ]);

        // --- 4. --- CREATE THE EXAM ---
        $adultCategory = ExamCategory::where('slug', 'adult')->first();

        $exam = Exam::updateOrCreate(
            ['title' => 'Global Proficiency Demo'],
            [
                'exam_category_id' => $adultCategory ? $adultCategory->id : null,
                'description' => 'A comprehensive demonstration exam showcasing all item types and adaptive levels.',
                'time_limit' => 60,
                'is_active' => true,
            ]
        );

        $exam->skills()->sync([
            $skills['Reading']->id => ['order_index' => 1, 'duration' => 15],
            $skills['Listening']->id => ['order_index' => 2, 'duration' => 15],
            $skills['Structure']->id => ['order_index' => 3, 'duration' => 15],
            $skills['Writing']->id => ['order_index' => 4, 'duration' => 15],
        ]);
    }
}
