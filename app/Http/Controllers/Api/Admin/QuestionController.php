<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Question\StoreQuestionRequest;
use App\Http\Requests\Admin\Question\UpdateQuestionRequest;
use App\Http\Requests\Admin\Question\BulkUpdateQuestionLevelRequest;
use App\Http\Requests\Admin\Question\UploadQuestionMediaRequest;
use App\Models\Question;
use App\Models\Passage;
use App\Models\Skill;
use App\Services\QuestionAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class QuestionController extends Controller
{
    public function __construct(private readonly QuestionAdminService $adminService) {}

    /**
     * Get all questions with skill info.
     */
    public function index(Request $request)
    {
        $query = Question::with([
            'skill', 'options', 'passage', 'level',
            'exam:id,title',
            'creator:id,first_name,last_name',
            'updater:id,first_name,last_name',
        ]);

        if ($request->has('skill_id') && $request->skill_id !== 'null' && $request->skill_id !== null) {
            $query->where('skill_id', $request->skill_id);
        }

        if ($request->has('exam_id') && $request->exam_id !== 'null' && $request->exam_id !== null) {
            $query->where('exam_id', $request->exam_id);
        }

        if ($request->has('level_id') && $request->level_id !== 'null' && $request->level_id !== null) {
            $levelVal = $request->level_id;
            $query->where(function ($q) use ($levelVal) {
                $q->where('level_id', $levelVal)
                  ->orWhereHas('level', fn($lq) => $lq->where('level_number', $levelVal));
            });
        }

        if ($request->has('level_number') && $request->level_number !== 'null' && $request->level_number !== null) {
            $levelNum = $request->level_number;
            $query->whereHas('level', fn($lq) => $lq->where('level_number', $levelNum));
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('exam_id');
        }

        return response()->json($query->get());
    }

    /**
     * Store a new batch of questions (with passage and options).
     */
    public function store(StoreQuestionRequest $request)
    {
        // \Log::info('Question store request', [
        //     'exam_id'  => $request->exam_id,
        //     'skill_id' => $request->skill_id,
        //     'all_keys' => array_keys($request->all()),
        // ]);

        // 1. Merge JSON-encoded questions with uploaded files
        $mergedData = $this->adminService->mergeJsonWithFiles($request);
        $request->merge(['questions' => $mergedData['questions']]);

        // 2. Points budget check
        if ($error = $this->adminService->checkPointsBudget( $request->questions, $request->exam_id, $request->skill_id )) 
        {
            return response()->json($error, 422);
        }

        // 3. Options logic check
        if ($error = $this->adminService->validateOptionsLogic($request->questions)) {
            return response()->json($error, 422);
        }

        // 4. Persist
        return DB::transaction(function () use ($request) {
            $passageId = $this->adminService->handlePassageStore($request);
            $level     = $this->adminService->resolveLevel($request->skill_id, $request->level_id);
            $ids       = $this->adminService->createBatch($request, $passageId, $level);

            return response()->json([
                'message'      => count($ids) . ' questions and passage created successfully.',
                'question_ids' => $ids,
                'passage_id'   => $passageId,
            ], 201);
        });
    }

    /**
     * Get a single question with full context.
     */
    public function show(Question $question)
    {
        return response()->json(
            $question->load(['options', 'skill', 'passage.questions.options', 'level', 'creator:id,first_name,last_name', 'updater:id,first_name,last_name'])
        );
    }

    /**
     * Update a batch of questions (with passage and options).
     */
    public function update(UpdateQuestionRequest $request, Question $question)
    {
        // 1. Merge JSON-encoded questions with uploaded files
        $mergedData = $this->adminService->mergeJsonWithFiles($request);
        $request->merge(['questions' => $mergedData['questions']]);

        // 2. Points budget check (exclude the questions being updated)
        $updatingIds = collect($request->questions)->pluck('id')->filter()->toArray();
        if ($error = $this->adminService->checkPointsBudget(
            $request->questions,
            $request->exam_id,
            $request->skill_id,
            $updatingIds
        )) {
            return response()->json($error, 422);
        }

        // 3. Persist
        return DB::transaction(function () use ($request, $question) {
            $passageId    = $this->adminService->handlePassageUpdate($request, $question);
            $level        = $this->adminService->resolveLevel($request->skill_id, $request->level_id);
            $lastInstance = $this->adminService->updateBatch($request, $question, $passageId, $level);

            return response()->json([
                'message'  => 'Batch updated successfully.',
                'question' => $lastInstance,
            ]);
        });
    }

    /**
     * Delete a question (and its entire passage group if applicable).
     */
    public function destroy(Question $question)
    {
        return DB::transaction(function () use ($question) {
            if ($question->passage_id) {
                $passageId        = $question->passage_id;
                $passageQuestions = Question::where('passage_id', $passageId)->get();

                foreach ($passageQuestions as $pQuestion) {
                    $pQuestion->options()->delete();
                    $pQuestion->delete();
                }

                $passage = Passage::find($passageId);
                $passage?->delete();
            } else {
                $question->options()->delete();
                $question->delete();
            }

            return response()->json(['message' => 'Question deleted successfully.']);
        });
    }

    /**
     * Get all questions for a specific skill.
     */
    public function indexBySkill(Skill $skill)
    {
        return response()->json(
            Question::where('skill_id', $skill->id)
                ->withCount('options')
                ->latest()
                ->get()
        );
    }

    /**
     * Bulk update difficulty level for multiple questions.
     */
    public function bulkUpdateLevel(BulkUpdateQuestionLevelRequest $request)
    {
        $validated = $request->validated();

        $firstQuestion = Question::find($validated['question_ids'][0]);
        if (!$firstQuestion) {
            return response()->json(['message' => 'Questions not found.'], 404);
        }

        $level = $this->adminService->resolveLevel($firstQuestion->skill_id, $validated['level_id']);

        Question::whereIn('id', $validated['question_ids'])
            ->update(['level_id' => $level->id]);

        return response()->json(['message' => 'Questions updated successfully.']);
    }

    /**
     * Get unique group tags for questions belonging to a specific skill.
     */
    public function getTagsBySkill(Skill $skill)
    {
        $tags = Question::where('skill_id', $skill->id)
            ->whereNotNull('group_tag')
            ->where('group_tag', '!=', '')
            ->distinct()
            ->pluck('group_tag');

        return response()->json($tags);
    }

    /**
     * Duplicate a question (and its full passage group if applicable).
     */
    public function duplicate(Question $question)
    {
        $newQuestion = $this->adminService->duplicateQuestion($question);

        $message = $question->passage_id
            ? 'Passage and all associated questions duplicated successfully.'
            : 'Question duplicated successfully.';

        return response()->json([
            'message'  => $message,
            'question' => $newQuestion->load(['options', 'skill', 'exam:id,title', 'creator:id,first_name,last_name']),
        ], 201);
    }

    /**
     * Get question for preview (same as show, called from frontend).
     */
    public function preview(Question $question)
    {
        return response()->json(
            $question->load(['options', 'skill', 'passage.questions.options', 'level', 'exam:id,title', 'creator:id,first_name,last_name'])
        );
    }

    /**
     * Standalone media upload for the Exam Constructor.
     */
    public function uploadMedia(UploadQuestionMediaRequest $request)
    {
        $validated = $request->validated();

        $path = $request->file('file')->store('questions', 'public');

        return response()->json([
            'path' => $path,
            'url'  => asset('storage/' . $path),
        ]);
    }

    /**
     * Stream a PDF file for interactive PDF questions.
     */
    public function streamPdf(Question $question)
    {
        $path = $question->pdf_path ?? $question->media_path;

        if (!$path || !Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'PDF file not found'], 404);
        }

        $file = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path) ?? 'application/pdf';

        return response($file, 200)
            ->header('Content-Type', $mime)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization, X-Requested-With');
    }
}
