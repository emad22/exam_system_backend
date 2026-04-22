<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    /**
     * Get all exams with language info
     */
    public function index()
    {
        $exams = Exam::with(['language', 'category', 'skills'])
            ->withCount(['attempts', 'questions', 'skills'])
            ->latest()
            ->get();

        // Attach breakdown logic
        $exams->each(function($exam) {
            $exam->breakdown = DB::table('exam_questions')
                ->join('questions', 'questions.id', '=', 'exam_questions.question_id')
                ->join('levels', 'levels.id', '=', 'questions.level_id')
                ->where('exam_questions.exam_id', $exam->id)
                ->select('questions.skill_id', 'levels.level_number as level_id', DB::raw('count(*) as count'))
                ->groupBy('questions.skill_id', 'levels.level_number')
                ->get();
        });

        return response()->json($exams);
    }

    /**
     * Store new Exam (Phase 5)
     */
    /**
     * Store new Exam
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'language_id' => 'required|exists:languages,id',
            'passing_score' => 'required|numeric|min:0|max:100',
            'timer_type' => 'nullable|string',
            'time_limit' => 'nullable|integer',

            'skills' => 'required|array|min:1',
            'skills.*.skill_id' => 'required|exists:skills,id',
            'skills.*.duration' => 'required|integer|min:1',
            'skills.*.is_optional' => 'boolean',
            'skills.*.rules' => 'nullable|array',
            'skills.*.rules.*.level_id' => 'required|integer|min:1|max:9',
            'skills.*.rules.*.quantity' => 'required|integer|min:1',
            'skills.*.rules.*.randomize' => 'boolean',

            'question_ids' => 'nullable|array', // List of questions assigned directly
            'question_ids.*' => 'exists:questions,id'
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $exam = Exam::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'exam_category_id' => $validated['exam_category_id'],
                'language_id' => $validated['language_id'],
                'passing_score' => $validated['passing_score'],
                'timer_type' => $validated['timer_type'] ?? 'global',
                'time_limit' => $validated['time_limit'] ?? 0,
            ]);

            foreach ($validated['skills'] as $skill) {
                $exam->skills()->attach($skill['skill_id'], [
                    'duration' => $skill['duration'],
                    'is_optional' => $skill['is_optional'] ?? false
                ]);

                if (isset($skill['rules']) && is_array($skill['rules'])) {
                    foreach ($skill['rules'] as $rule) {
                        $exam->questionRules()->create([
                            'skill_id' => $skill['skill_id'],
                            'level_id' => $rule['level_id'],
                            'quantity' => $rule['quantity'],
                            'randomize' => $rule['randomize'] ?? true,
                        ]);
                    }
                }
            }

            // Sync direct question assignments
            if (!empty($validated['question_ids'])) {
                $exam->questions()->sync($validated['question_ids']);
            }

            return response()->json([
                'message' => 'Exam created successfully.',
                'exam' => $exam->load(['skills', 'questions'])
            ], 201);
        });
    }

    /**
     * Get a single exam
     */
    public function show(Exam $exam)
    {
        return response()->json($exam->load(['skills', 'questionRules', 'language', 'category', 'questions.options', 'questions.level', 'questions.passage']));
    }

    /**
     * Update an existing exam
     */
    public function update(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'exam_category_id' => 'required|exists:exam_categories,id',
            'language_id' => 'required|exists:languages,id',
            'passing_score' => 'required|numeric|min:0|max:100',
            'timer_type' => 'nullable|string',
            'time_limit' => 'nullable|integer',

            'skills' => 'required|array|min:1',
            'skills.*.skill_id' => 'required|exists:skills,id',
            'skills.*.duration' => 'required|integer|min:1',
            'skills.*.is_optional' => 'boolean',
            'skills.*.rules' => 'nullable|array',
            'skills.*.rules.*.level_id' => 'required|integer|min:1|max:9',
            'skills.*.rules.*.quantity' => 'required|integer|min:1',
            'skills.*.rules.*.randomize' => 'boolean',

            'question_ids' => 'nullable|array',
            'question_ids.*' => 'exists:questions,id'
        ]);

        return DB::transaction(function () use ($validated, $request, $exam) {
            $exam->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'exam_category_id' => $validated['exam_category_id'],
                'language_id' => $validated['language_id'],
                'passing_score' => $validated['passing_score'],
                'timer_type' => $validated['timer_type'] ?? $exam->timer_type,
                'time_limit' => $validated['time_limit'] ?? $exam->time_limit,
            ]);

            // Sync Skills
            $pivotSkills = [];
            foreach ($validated['skills'] as $skill) {
                $pivotSkills[$skill['skill_id']] = [
                    'duration' => $skill['duration'],
                    'is_optional' => $skill['is_optional'] ?? false
                ];
            }
            $exam->skills()->sync($pivotSkills);

            // Sync Rules
            $exam->questionRules()->delete();
            foreach ($validated['skills'] as $skill) {
                if (isset($skill['rules']) && is_array($skill['rules'])) {
                    foreach ($skill['rules'] as $rule) {
                        $exam->questionRules()->create([
                            'skill_id' => $skill['skill_id'],
                            'level_id' => $rule['level_id'],
                            'quantity' => $rule['quantity'],
                            'randomize' => $rule['randomize'] ?? true,
                        ]);
                    }
                }
            }

            // Sync direct question assignments
            if (isset($validated['question_ids'])) {
                $exam->questions()->sync($validated['question_ids']);
            }

            return response()->json([
                'message' => 'Exam updated successfully.',
                'exam' => $exam->load(['skills', 'questions'])
            ]);
        });
    }

    /**
     * Delete an exam
     */
    public function destroy(Exam $exam)
    {
        return DB::transaction(function () use ($exam) {
            // Detach relationships in pivot tables
            $exam->questions()->detach();
            $exam->skills()->detach();
            
            // Delete metadata
            $exam->questionRules()->delete();
            $exam->studentConfigs()->delete();
            $exam->attempts()->delete(); 

            $exam->delete();

            return response()->json(['message' => 'Exam has been purged.']);
        });
    }

    /**
     * Set an exam as the default for its type
     */
    public function setDefault(Exam $exam)
    {
        // 1. Capture the ID of the old default for this same category
        $oldDefaultId = Exam::where('exam_category_id', $exam->exam_category_id)
            ->where('is_default', true)
            ->value('id');

        // 2. Unset other defaults of the same category
        Exam::where('exam_category_id', $exam->exam_category_id)
            ->update(['is_default' => false]);

        // 3. Set new default
        $exam->update(['is_default' => true]);

        // 4. Migrate existing students who were on the old default
        if ($oldDefaultId && $oldDefaultId != $exam->id) {
            \App\Models\StudentExamConfig::where('exam_id', $oldDefaultId)
                ->update([
                    'exam_id' => $exam->id,
                    'want_reading' => $exam->default_want_reading,
                    'want_listening' => $exam->default_want_listening,
                    'want_grammar' => $exam->default_want_grammar,
                    'want_writing' => $exam->default_want_writing,
                    'want_speaking' => $exam->default_want_speaking,
                ]);
        }

        return response()->json([
            'message' => "Exam '{$exam->title}' is now the global default. Existing students have been migrated."
        ]);
    }
}
