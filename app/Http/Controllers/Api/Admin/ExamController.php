<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Skill;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    /**
     * Get all exams with language info
     */
    public function index()
    {
        return response()->json(Exam::with(['language', 'category'])->withCount('attempts')->latest()->get());
    }

    /**
     * Store new Exam (Phase 5)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',

            'exam_category_id' => 'required|exists:exam_categories,id',
            'passing_score' => 'required|numeric|min:0|max:100',

            'skills' => 'required|array|min:1',
            'skills.*.skill_id' => 'required|exists:skills,id',
            'skills.*.duration' => 'required|integer|min:1',
            'skills.*.is_optional' => 'boolean',
            'skills.*.rules' => 'nullable|array',
        ]);

        // Automatically set the default_want_... boolean flags based on incoming skills
        $skillNames = Skill::whereIn('id', collect($validated['skills'])->pluck('skill_id'))->pluck('name')->map(fn($n) => strtolower($n))->toArray();
        
        $exam = Exam::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,

            'exam_category_id' => $validated['exam_category_id'],
            'passing_score' => $validated['passing_score'],

            'default_want_reading' => in_array('reading', $skillNames),
            'default_want_listening' => in_array('listening', $skillNames),
            'default_want_grammar' => in_array('grammar', $skillNames),
            'default_want_writing' => in_array('writing', $skillNames),
            'default_want_speaking' => in_array('speaking', $skillNames),
        ]);

        foreach ($validated['skills'] as $skill) {
            $exam->skills()->attach($skill['skill_id'], [
                'duration' => $skill['duration'],
                'is_optional' => $skill['is_optional'] ?? false
            ]);

            // Save question rules if provided
            if (isset($skill['rules']) && is_array($skill['rules'])) {
                foreach ($skill['rules'] as $rule) {
                    $exam->questionRules()->create([
                        'skill_id' => $skill['skill_id'],
                        'difficulty_level' => $rule['difficulty_level'] ?? null,
                        'group_tag' => $rule['group_tag'] ?? null,
                        'quantity' => $rule['quantity'] ?? 5,
                        'randomize' => $rule['randomize'] ?? true,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Exam created successfully with skills.',
            'exam' => $exam->load('skills')
        ], 201);
    }

    /**
     * Get a single exam
     */
    public function show(Exam $exam)
    {
        $exam->load(['skills', 'questionRules', 'language', 'category']);
        
        // Attach questions that belong to this exam via the group_tag
        $exam->questions = \App\Models\Question::where('group_tag', $exam->title)
            ->with('options')
            ->get();

        return response()->json($exam);
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
            'passing_score' => 'required|numeric|min:0|max:100',
            'is_adaptive' => 'boolean',
            'skills' => 'required|array|min:1',
            'skills.*.skill_id' => 'required|exists:skills,id',
            'skills.*.duration' => 'required|integer|min:1',
            'skills.*.is_optional' => 'boolean',
            'skills.*.rules' => 'nullable|array',
        ]);

        // Clean up existing questions for this exam (by old tag) before frontend re-saves
        // This prevents duplicates because the frontend will POST all current questions in localQuestions
        \App\Models\Question::where('group_tag', $exam->title)->delete();

        $skillNames = Skill::whereIn('id', collect($validated['skills'])->pluck('skill_id'))->pluck('name')->map(fn($n) => strtolower($n))->toArray();

        $exam->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'exam_category_id' => $validated['exam_category_id'],
            'passing_score' => $validated['passing_score'],
            'is_adaptive' => $validated['is_adaptive'] ?? false,
            'default_want_reading' => in_array('reading', $skillNames),
            'default_want_listening' => in_array('listening', $skillNames),
            'default_want_grammar' => in_array('grammar', $skillNames),
            'default_want_writing' => in_array('writing', $skillNames),
            'default_want_speaking' => in_array('speaking', $skillNames),
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

        // Sync Rules (Delete old, create new)
        $exam->questionRules()->delete();
        foreach ($validated['skills'] as $skill) {
            if (isset($skill['rules']) && is_array($skill['rules'])) {
                foreach ($skill['rules'] as $rule) {
                    $exam->questionRules()->create([
                        'skill_id' => $skill['skill_id'],
                        'difficulty_level' => $rule['difficulty_level'] ?? null,
                        'group_tag' => $rule['group_tag'] ?? null,
                        'quantity' => $rule['quantity'] ?? 5,
                        'randomize' => $rule['randomize'] ?? true,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Exam updated successfully.',
            'exam' => $exam->load('skills')
        ]);
    }

    /**
     * Delete an exam and all its associated questions (Cascade)
     */
    public function destroy(Exam $exam)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($exam) {
            // 1. Delete associated questions (Legacy mapping via group_tag)
            // Use each() to ensure we're working with model instances for linting & events
            \App\Models\Question::where('group_tag', $exam->title)->get()->each(function($question) {
                $question->options()->delete();
                $question->delete();
            });

            // 2. Delete related Exam metadata
            $exam->questionRules()->delete(); // Rules
            $exam->studentConfigs()->delete(); // Assigned to students
            
            // 3. Delete Attempts (Warning: This wipes all student results for this exam)
            $exam->attempts()->delete(); 

            // 4. Detach Skills (Pivot)
            $exam->skills()->detach();

            // 5. Delete the Exam itself
            $exam->delete();

            return response()->json([
                'message' => 'Exam and all its associated questions and results have been purged from the system.'
            ]);
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
