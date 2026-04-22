<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\ExamQuestionRule;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Level;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SkillController extends Controller
{
    /**
     * Get all skills
     */
    public function index()
    {
        return response()->json(Skill::withCount(['questions', 'levels'])->orderBy('name')->get());
    }

    /**
     * Store new Skill
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:skills',
            'short_code' => 'nullable|string|max:10|unique:skills',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
        ]);

        $skill = Skill::create($validated);

        return response()->json([
            'message' => 'Skill created successfully.',
            'skill' => $skill
        ], 201);
    }

    /**
     * Update existing Skill
     */
    public function update(Request $request, Skill $skill)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:skills,name,' . $skill->id,
            'short_code' => 'sometimes|nullable|string|max:10|unique:skills,short_code,' . $skill->id,
            'description' => 'sometimes|nullable|string',
            'icon' => 'sometimes|nullable|string',
        ]);

        $skill->update($validated);

        return response()->json([
            'message' => 'Skill updated successfully.',
            'skill' => $skill
        ]);
    }

    /**
     * Delete existing Skill
     */
    public function destroy(Request $request, Skill $skill)
    {
        DB::beginTransaction();
        try {
            // Delete related questions and rules
            ExamQuestionRule::where('skill_id', $skill->id)->delete();
            
            // Delete questions
            $questionIds = Question::where('skill_id', $skill->id)->pluck('id');
            QuestionOption::whereIn('question_id', $questionIds)->delete();
            Question::whereIn('id', $questionIds)->delete();

            // Clear from exams
            DB::table('exam_skill')->where('skill_id', $skill->id)->delete();
            
            // Clear levels
            Level::where('skill_id', $skill->id)->delete();

            $skill->delete();

            DB::commit();
            return response()->json(['message' => 'Skill and all related content deleted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete skill. Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Level Management - Get skills with their levels
     */
    public function getSkillsWithLevels()
    {
        return response()->json(Skill::with('levels')->get());
    }

    /**
     * Level Management - Get specific skill with its levels
     */
    public function getSkillWithLevels(Skill $skill)
    {
        return response()->json($skill->load('levels'));
    }

    /**
     * Bulk update/create levels for a skill
     */
    public function bulkUpdateLevels(Request $request, Skill $skill)
    {
        $validated = $request->validate([
            'levels' => 'required|array',
            'levels.*.id' => 'nullable|exists:levels,id',
            'levels.*.name' => 'required|string|max:255',
            'levels.*.level_number' => 'required|integer',
            'levels.*.min_score' => 'required|integer',
            'levels.*.max_score' => 'required|integer',
            'levels.*.pass_threshold' => 'required|integer|min:0|max:100',
            'levels.*.instructions' => 'nullable|string',
            'levels.*.default_question_count' => 'nullable|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $incomingIds = array_filter(array_column($validated['levels'], 'id'));

            // Sync: Optional - Delete levels not in the request? 
            // Better to handle deletion explicitly through the destroy method to avoid accidental data loss.
            
            foreach ($validated['levels'] as $levelData) {
                Level::updateOrCreate(
                    ['id' => $levelData['id'] ?? null, 'skill_id' => $skill->id],
                    [
                        'name' => $levelData['name'],
                        'level_number' => $levelData['level_number'],
                        'min_score' => $levelData['min_score'],
                        'max_score' => $levelData['max_score'],
                        'pass_threshold' => $levelData['pass_threshold'],
                        'instructions' => $levelData['instructions'] ?? null,
                        'default_question_count' => $levelData['default_question_count'] ?? 2,
                    ]
                );
            }
            DB::commit();
            return response()->json(['message' => 'Levels synchronized successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to synchronize levels: ' . $e->getMessage()], 500);
        }
    }
}
