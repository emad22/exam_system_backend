<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAttemptLevel;
use App\Models\ExamAttemptSkill;
use App\Models\Skill;
use App\Models\ExamQuestionRule;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Level;
use App\Http\Requests\Admin\Skill\StoreSkillRequest;
use App\Http\Requests\Admin\Skill\UpdateSkillRequest;
use App\Http\Requests\Admin\Skill\BulkUpdateLevelsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SkillController extends Controller
{
    /**
     * Get all skills
     */
    public function index()
    {
        $this->authorize('viewAny', Skill::class);
        return response()->json(Skill::withCount(['questions', 'levels'])->orderBy('name')->get());
    }

    /**
     * Store new Skill
     */
    public function store(StoreSkillRequest $request)
    {
        $this->authorize('create', Skill::class);
        $validated = $request->validated();

        $skill = Skill::create($validated);

        if (!empty($validated['levels_count']) && $validated['levels_count'] > 0) {
            for ($i = 1; $i <= $validated['levels_count']; $i++) {
                Level::create([
                    'skill_id' => $skill->id,
                    'name' => "Level $i",
                    'level_number' => $i,
                    'min_score' => ($i - 1) * 100 + 1,
                    'max_score' => $i * 100,
                    'pass_threshold' => 70,
                    'default_question_count' => 0,
                    'is_active' => true,
                    'allows_retry' => false,
                    'is_random' => false,
                    'default_passage_quantity' => 0,
                    'default_standalone_quantity' => 0,
                ]);
            }
        }

        return response()->json([
            'message' => 'Skill and initial levels created successfully.',
            'skill' => $skill->load('levels')
        ], 201);
    }

    /**
     * Update existing Skill
     */
    public function update(UpdateSkillRequest $request, Skill $skill)
    {
        $this->authorize('update', $skill);
        $validated = $request->validated();

        if (array_key_exists('levels_count', $validated)) {
            $newLevelsCount = (int)$validated['levels_count'];
            $currentLevelsCount = $skill->levels()->count();

            if ($newLevelsCount > $currentLevelsCount) {
                // Generate additional levels
                for ($i = $currentLevelsCount + 1; $i <= $newLevelsCount; $i++) {
                    Level::create([
                        'skill_id' => $skill->id,
                        'name' => "Level $i",
                        'level_number' => $i,
                        'min_score' => ($i - 1) * 100 + 1,
                        'max_score' => $i * 100,
                        'pass_threshold' => 70,
                        'default_question_count' => 0,
                        'is_active' => true,
                        'allows_retry' => false,
                        'is_random' => false,
                        'default_passage_quantity' => 0,
                        'default_standalone_quantity' => 0,
                    ]);
                }
            } elseif ($newLevelsCount < $currentLevelsCount) {
                // Safely reduce levels
                $levelsToDelete = $skill->levels()->where('level_number', '>', $newLevelsCount)->get();
                foreach ($levelsToDelete as $level) {
                    if ($level->questions()->exists()) {
                        return response()->json([
                            'message' => "Cannot reduce levels count to {$newLevelsCount}. Level {$level->level_number} has associated questions."
                        ], 422);
                    }
                }
                foreach ($levelsToDelete as $level) {
                    if ($level->instructions_audio) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($level->instructions_audio);
                    }
                    $level->delete();
                }
            }
        }

        unset($validated['levels_count']);

        $skill->update($validated);

        return response()->json([
            'message' => 'Skill updated successfully.',
            'skill' => $skill->loadCount(['questions', 'levels'])
        ]);
    }

    /**
     * Delete existing Skill
     */
    public function destroy(Request $request, Skill $skill)
    {
        $this->authorize('delete', $skill);
        DB::beginTransaction();
        try {
            // Delete related questions and rules
            ExamQuestionRule::where('skill_id', $skill->id)->delete();

            // Delete questions
            // $questionIds = Question::where('skill_id', $skill->id)->pluck('id');
            // QuestionOption::whereIn('question_id', $questionIds)->delete();
            // Question::whereIn('id', $questionIds)->delete();

            // Clear from exams
            DB::table('exam_skill')->where('skill_id', $skill->id)->delete();

            // Clear levels
            Level::where('skill_id', $skill->id)->delete();

            // Clear attempt results
            ExamAttemptLevel::where('skill_id', $skill->id)->delete();
            ExamAttemptSkill::where('skill_id', $skill->id)->delete();

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
        $this->authorize('viewAny', Skill::class);
        return response()->json(Skill::with('levels')->get());
    }

    /**
     * Level Management - Get specific skill with its levels
     */
    public function getSkillWithLevels(Skill $skill)
    {
        $this->authorize('view', $skill);
        return response()->json($skill->load('levels'));
    }

    /**
     * Bulk update/create levels for a skill
     */
    public function bulkUpdateLevels(BulkUpdateLevelsRequest $request, Skill $skill)
    {
        $this->authorize('bulkUpdateLevels', Skill::class);
        $validated = $request->validated();

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
