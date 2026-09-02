<?php

namespace App\Services;

use App\Models\Level;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles admin CRUD operations for Questions (create, update, duplicate).
 *
 * NOTE: This is separate from QuestionService which handles exam-taking flow
 * (fetching questions for active exam attempts).
 */
class QuestionAdminService
{
    // ─── JSON + File Merge ───────────────────────────────────────────────────

    /**
     * Merge a JSON-encoded `questions` field with uploaded files from the same request.
     *
     * When the frontend sends multipart/form-data it JSON-encodes the questions
     * array and attaches files separately. This method reunites them.
     */
    public function mergeJsonWithFiles(Request $request): array
    {
        $data          = $request->all();
        $questionsJson = $request->input('questions');

        if (!is_string($questionsJson)) {
            return $data;
        }

        $decodedQuestions = json_decode($questionsJson, true);
        if (!is_array($decodedQuestions)) {
            return $data;
        }

        $files = $request->file('questions') ?? [];
        if (is_array($files)) {
            foreach ($files as $qIndex => $fileArray) {
                if (!isset($decodedQuestions[$qIndex]) || !is_array($fileArray)) {
                    continue;
                }

                // Merge question-level files (q_media_file, q_audio_file, q_image_file, q_pdf_file)
                foreach ($fileArray as $fileKey => $fileValue) {
                    if ($fileKey === 'options') {
                        continue;
                    }
                    $decodedQuestions[$qIndex][$fileKey] = $fileValue;
                }

                // Merge option-level files without overwriting existing text/is_correct
                if (isset($fileArray['options']) && is_array($fileArray['options'])) {
                    foreach ($fileArray['options'] as $oIndex => $optFiles) {
                        if (isset($decodedQuestions[$qIndex]['options'][$oIndex])) {
                            foreach ($optFiles as $optKey => $optValue) {
                                $decodedQuestions[$qIndex]['options'][$oIndex][$optKey] = $optValue;
                            }
                        }
                    }
                }
            }
        }

        $data['questions'] = $decodedQuestions;
        return $data;
    }

    // ─── Business Rule Checks ────────────────────────────────────────────────

    /**
     * Check that the total points for writing/speaking questions do not exceed
     * the exam_skill cap. Returns an error response payload or null if OK.
     *
     * @param  int[]|null  $excludeIds  Question IDs to exclude from the existing sum (for updates).
     */
    public function checkPointsBudget( array $questions,  int $examId, int $skillId, ?array $excludeIds = null ): ?array {
        $skill = Skill::find($skillId);
        if (!$skill) {
            return null;
        }

        $code        = strtoupper($skill->short_code ?? '');
        $name        = strtolower($skill->name ?? '');
        $isProductive = in_array($code, ['W', 'S', 'WR', 'SP', 'WRIT', 'SPEAK', 'WRITING', 'SPEAKING'])
            || str_contains($name, 'writ')
            || str_contains($name, 'speak');

        if (!$isProductive) {
            return null;
        }

        $maxPoints = DB::table('exam_skill')
            ->where('exam_id', $examId)
            ->where('skill_id', $skillId)
            ->value('max_points') ?? 0;

        if ($maxPoints <= 0) {
            return null;
        }

        $existingQuery = Question::where('exam_id', $examId)->where('skill_id', $skillId);
        if (!empty($excludeIds)) {
            $existingQuery->whereNotIn('id', $excludeIds);
        }
        $existing = $existingQuery->sum('points');
        $newTotal = collect($questions)->sum('points');

        if (($existing + $newTotal) > $maxPoints) {
            $remaining = max(0, $maxPoints - $existing);
            return [
                'message' => "Total points exceed the skill cap of {$maxPoints}. Remaining budget: {$remaining} pts.",
                'errors'  => ['points' => ["Cannot exceed the {$maxPoints}pt cap. Remaining: {$remaining} pts."]],
            ];
        }

        return null;
    }

    /**
     * Validate MCQ-style questions have at least the required number of options
     * with a correct answer marked. Returns error response payload or null if OK.
     */
    public function validateOptionsLogic(array $questions): ?array
    {
        $requiresOptions = ['mcq', 'true_false', 'drag_drop', 'word_selection', 'click_word', 'fill_blank', 'matching', 'ordering', 'highlight', 'listening'];

        foreach ($questions as $index => $qData) {
            $minOptions = ($qData['type'] === 'click_word') ? 1 : 2;

            if (!in_array($qData['type'], $requiresOptions)) {
                continue;
            }

            if (!isset($qData['options']) || count($qData['options']) < $minOptions) {
                return ['message' => "Options are required for question #" . ($index + 1)];
            }

            $hasCorrect = collect($qData['options'])->contains('is_correct', true);
            if (!$hasCorrect) {
                return ['message' => "You must select a correct answer for question #" . ($index + 1)];
            }
        }

        return null;
    }

    // ─── Level Resolution ────────────────────────────────────────────────────

    /**
     * Resolve or create a Level record by skill + level number.
     */
    public function resolveLevel(int $skillId, ?int $levelNumber): Level
    {
        $levelNumber = $levelNumber ?? 1;

        return Level::firstOrCreate(
            ['skill_id' => $skillId, 'level_number' => $levelNumber],
            [
                'name'                       => 'Level ' . $levelNumber,
                'min_score'                  => 0,
                'max_score'                  => 100,
                'default_standalone_quantity' => 0,
                'default_passage_quantity'   => 0,
                'default_question_count'     => 0,
            ]
        );
    }

    // ─── Passage Handling ────────────────────────────────────────────────────

    /**
     * Resolve the passage ID for a store operation.
     * Returns null when passage_mode is 'none'.
     */
    public function handlePassageStore(Request $request): ?int
    {
        if ($request->passage_mode === 'existing') {
            return $request->passage_id;
        }

        if ($request->passage_mode === 'new') {
            $pMediaPath = $request->hasFile('p_media_file')
                ? $request->file('p_media_file')->store('passages', 'public')
                : null;
            $pAudioPath = $request->hasFile('p_audio_file')
                ? $request->file('p_audio_file')->store('passages/audio', 'public')
                : null;
            $pImagePath = $request->hasFile('p_image_file')
                ? $request->file('p_image_file')->store('passages/images', 'public')
                : null;

            $passage = Passage::create([
                'type'                => $request->passage_type,
                'title'               => $request->passage_title,
                'content'             => $request->passage_content,
                'general_instructions' => $request->input('passage_general_instructions'),
                'media_path'          => $pMediaPath,
                'audio_path'          => $pAudioPath,
                'image_path'          => $pImagePath,
                'image_width'         => $request->p_image_width,
                'image_height'        => $request->p_image_height,
                'questions_limit'     => $request->passage_questions_limit,
                'is_random'           => $request->boolean('passage_is_random'),
            ]);

            return $passage->id;
        }

        return null;
    }

    /**
     * Resolve (and optionally update) the passage ID for an update operation.
     */
    public function handlePassageUpdate(Request $request, Question $question): ?int
    {
        if ($request->passage_mode === 'none') {
            return null;
        }

        if ($request->passage_mode === 'existing') {
            $passageId = $request->passage_id;

            if ($passageId) {
                $passage = Passage::find($passageId);
                if ($passage) {
                    $pMediaPath = $request->boolean('clear_p_media')
                        ? null
                        : ($request->hasFile('p_media_file') ? $request->file('p_media_file')->store('passages', 'public') : $passage->media_path);

                    $pAudioPath = $request->boolean('clear_p_audio')
                        ? null
                        : ($request->hasFile('p_audio_file') ? $request->file('p_audio_file')->store('passages/audio', 'public') : $passage->audio_path);

                    $pImagePath = $request->boolean('clear_p_image')
                        ? null
                        : ($request->hasFile('p_image_file') ? $request->file('p_image_file')->store('passages/images', 'public') : $passage->image_path);

                    $passage->update([
                        'type'                 => $request->passage_type    ?? $passage->type,
                        'title'                => $request->passage_title   ?? $passage->title,
                        'content'              => $request->passage_content ?? $passage->content,
                        'general_instructions' => $request->has('passage_general_instructions')
                            ? $request->input('passage_general_instructions')
                            : $passage->general_instructions,
                        'media_path'           => $pMediaPath,
                        'audio_path'           => $pAudioPath,
                        'image_path'           => $pImagePath,
                        'image_width'          => $request->has('p_image_width')  ? $request->p_image_width  : $passage->image_width,
                        'image_height'         => $request->has('p_image_height') ? $request->p_image_height : $passage->image_height,
                        'questions_limit'      => $request->passage_questions_limit ?? $passage->questions_limit,
                        'is_random'            => $request->boolean('passage_is_random'),
                    ]);
                }
            }

            return $passageId;
        }

        if ($request->passage_mode === 'new') {
            $pMediaPath = $request->hasFile('p_media_file')
                ? $request->file('p_media_file')->store('passages', 'public')
                : null;

            $passage = Passage::create([
                'type'                => $request->passage_type,
                'title'               => $request->passage_title,
                'content'             => $request->passage_content,
                'general_instructions' => $request->input('passage_general_instructions'),
                'media_path'          => $pMediaPath,
                'image_width'         => $request->p_image_width,
                'image_height'        => $request->p_image_height,
                'questions_limit'     => $request->passage_questions_limit,
                'is_random'           => $request->boolean('passage_is_random'),
            ]);

            return $passage->id;
        }

        return $question->passage_id;
    }

    // ─── Question Batch CRUD ─────────────────────────────────────────────────

    /**
     * Create a batch of questions (and their options) within a DB transaction.
     *
     * @return int[]  IDs of newly created questions.
     */
    public function createBatch(Request $request, ?int $passageId, Level $level): array
    {
        $createdIds = [];

        foreach ($request->questions as $index => $qData) {
            [$qMediaPath, $qAudioPath, $qImagePath, $qPdfPath] = $this->resolveQuestionFiles($request, $index);

            $question = Question::create([
                'skill_id'             => $request->skill_id,
                'exam_id'              => $request->exam_id,
                'level_id'             => $level->id,
                'passage_id'           => $passageId,
                'type'                 => $qData['type'],
                'instructions'         => $qData['instructions'] ?? null,
                'general_instructions' => $qData['general_instructions'] ?? null,
                'content'              => $qData['content'] ?? '',
                'media_path'           => $qMediaPath,
                'audio_path'           => $qAudioPath,
                'image_path'           => $qImagePath,
                'pdf_path'             => $qPdfPath,
                'image_width'          => $qData['image_width'] ?? null,
                'image_height'         => $qData['image_height'] ?? null,
                'points'               => $qData['points'] ?? 1,
                'sort_order'           => $qData['sort_order'] ?? 0,
                'min_words'            => $qData['min_words'] ?? null,
                'max_words'            => $qData['max_words'] ?? null,
                'created_by'           => $request->user()?->id,
            ]);

            $this->syncOptions($question, $qData, create: true);
            $createdIds[] = $question->id;
        }

        return $createdIds;
    }

    /**
     * Update a batch of questions (and their options) within a DB transaction.
     */
    public function updateBatch(Request $request, Question $pivotQuestion, ?int $passageId, Level $level): Question
    {
        $questionsData = $request->questions ?? [
            array_merge(
                $request->only(['type', 'content', 'instructions', 'general_instructions', 'points', 'min_words', 'max_words', 'options', 'image_width', 'image_height']),
                ['id' => $pivotQuestion->id]
            ),
        ];

        // Remove questions deleted in the UI from the passage
        if ($passageId) {
            $incomingIds = collect($questionsData)->pluck('id')->filter()->toArray();
            $existingIds = Question::where('passage_id', $passageId)->pluck('id')->toArray();
            $toDelete    = array_diff($existingIds, $incomingIds);

            if (!empty($toDelete)) {
                $questionsToDelete = Question::whereIn('id', $toDelete)->get();
                foreach ($questionsToDelete as $dq) {
                    $dq->options()->delete();
                    $dq->delete();
                }
            }
        }

        $lastInstance = $pivotQuestion;

        foreach ($questionsData as $index => $qData) {
            [$qMediaPath, $qAudioPath, $qImagePath, $qPdfPath] = $this->resolveQuestionFilesForUpdate($request, $index, $qData, count($questionsData));

            $qInstance = isset($qData['id']) ? Question::find($qData['id']) : new Question();
            if (!$qInstance) {
                $qInstance = new Question();
            }

            $attrs = [
                'skill_id'             => $request->skill_id,
                'exam_id'              => $request->exam_id,
                'level_id'             => $level->id,
                'passage_id'           => $passageId,
                'type'                 => $qData['type'],
                'instructions'         => $qData['instructions'] ?? null,
                'general_instructions' => $qData['general_instructions'] ?? null,
                'content'              => $qData['content'] ?? '',
                'image_width'          => array_key_exists('image_width', $qData)  ? $qData['image_width']  : null,
                'image_height'         => array_key_exists('image_height', $qData) ? $qData['image_height'] : null,
                'points'               => $qData['points'] ?? 1,
                'sort_order'           => $qData['sort_order'] ?? 0,
                'min_words'            => $qData['min_words'] ?? null,
                'max_words'            => $qData['max_words'] ?? null,
                'updated_by'           => $request->user()?->id,
            ];

            // Media path handling — keep existing if no new file and not clearing
            $attrs = $this->applyMediaPaths($attrs, $qData, $qInstance, $qMediaPath, $qAudioPath, $qImagePath, $qPdfPath);

            if (!$qInstance->exists) {
                $attrs['created_by'] = $request->user()?->id;
            }

            $qInstance->fill($attrs)->save();
            $this->syncOptions($qInstance, $qData, create: false);

            $lastInstance = $qInstance;
        }

        return $lastInstance->fresh(['options', 'passage.questions.options']);
    }

    /**
     * Duplicate a question (and its full passage if applicable).
     */
    public function duplicateQuestion(Question $question): Question
    {
        return DB::transaction(function () use ($question) {
            if ($question->passage_id) {
                return $this->duplicatePassageGroup($question);
            }
            return $this->duplicateStandalone($question);
        });
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /** Resolve file paths for a new question in a batch. */
    private function resolveQuestionFiles(Request $request, int $index): array
    {
        $qMediaPath = $request->hasFile("questions.{$index}.q_media_file")
            ? $request->file("questions.{$index}.q_media_file")->store('questions', 'public')
            : null;

        $qAudioPath = $request->hasFile("questions.{$index}.q_audio_file")
            ? $request->file("questions.{$index}.q_audio_file")->store('questions/audio', 'public')
            : null;

        $qImagePath = $request->hasFile("questions.{$index}.q_image_file")
            ? $request->file("questions.{$index}.q_image_file")->store('questions/images', 'public')
            : null;

        $qPdfPath = null;
        if ($request->hasFile("questions.{$index}.q_pdf_file")) {
            $qPdfPath = $request->file("questions.{$index}.q_pdf_file")->store('questions/pdfs', 'public');
        } elseif ($qMediaPath && str_ends_with(strtolower($qMediaPath), '.pdf')) {
            $qPdfPath = $qMediaPath;
        }

        return [$qMediaPath, $qAudioPath, $qImagePath, $qPdfPath];
    }

    /**
     * Resolve file paths for an update operation, also handling the single-question
     * case where files are sent without an index prefix.
     */
    private function resolveQuestionFilesForUpdate(Request $request, int $index, array $qData, int $batchSize): array
    {
        $fileKey  = "questions.{$index}.q_media_file";
        $audioKey = "questions.{$index}.q_audio_file";
        $imageKey = "questions.{$index}.q_image_file";
        $pdfKey   = "questions.{$index}.q_pdf_file";

        // Single-question update: files may be sent without index
        if ($batchSize === 1) {
            if (!$request->hasFile($fileKey)  && $request->hasFile('q_media_file'))  $fileKey  = 'q_media_file';
            if (!$request->hasFile($audioKey) && $request->hasFile('q_audio_file')) $audioKey = 'q_audio_file';
            if (!$request->hasFile($imageKey) && $request->hasFile('q_image_file')) $imageKey = 'q_image_file';
            if (!$request->hasFile($pdfKey)   && $request->hasFile('q_pdf_file'))   $pdfKey   = 'q_pdf_file';
        }

        $qMediaPath = $request->hasFile($fileKey)  ? $request->file($fileKey)->store('questions', 'public')         : null;
        $qAudioPath = $request->hasFile($audioKey) ? $request->file($audioKey)->store('questions/audio', 'public')  : null;
        $qImagePath = $request->hasFile($imageKey) ? $request->file($imageKey)->store('questions/images', 'public') : null;
        $qPdfPath   = null;

        if ($request->hasFile($pdfKey)) {
            $qPdfPath = $request->file($pdfKey)->store('questions/pdfs', 'public');
        } elseif ($qMediaPath && str_ends_with(strtolower($qMediaPath), '.pdf')) {
            $qPdfPath = $qMediaPath;
        }

        return [$qMediaPath, $qAudioPath, $qImagePath, $qPdfPath];
    }

    /**
     * Apply media path resolution (new upload / clear flag / keep existing).
     */
    private function applyMediaPaths(array $attrs, array $qData, Question $qInstance, ?string $qMediaPath, ?string $qAudioPath, ?string $qImagePath, ?string $qPdfPath): array
    {
        // media_path
        if ($qMediaPath) {
            $attrs['media_path'] = $qMediaPath;
        } elseif (filter_var($qData['clear_q_media'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $attrs['media_path'] = null;
        } elseif ($qInstance->exists) {
            $attrs['media_path'] = $qInstance->media_path;
        }

        // audio_path
        if ($qAudioPath) {
            $attrs['audio_path'] = $qAudioPath;
        } elseif (filter_var($qData['clear_q_audio'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $attrs['audio_path'] = null;
        } elseif ($qInstance->exists) {
            $attrs['audio_path'] = $qInstance->audio_path;
        }

        // image_path
        if ($qImagePath) {
            $attrs['image_path'] = $qImagePath;
        } elseif (filter_var($qData['clear_q_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $attrs['image_path'] = null;
        } elseif ($qInstance->exists) {
            $attrs['image_path'] = $qInstance->image_path;
        }

        // pdf_path
        if ($qPdfPath) {
            $attrs['pdf_path'] = $qPdfPath;
        } elseif (filter_var($qData['clear_q_pdf'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $attrs['pdf_path'] = null;
        } elseif ($qInstance->exists) {
            $attrs['pdf_path'] = $qInstance->pdf_path;
        }

        return $attrs;
    }

    /**
     * Create or update options for a question.
     *
     * @param  bool  $create  When true, all options are created fresh. When false,
     *                        existing options are updated and orphans removed.
     */
    private function syncOptions(Question $question, array $qData, bool $create): void
    {
        $noOptionsTypes = ['writing', 'speaking', 'speaking_live', 'upload'];
        if (empty($qData['options']) || in_array($qData['type'], $noOptionsTypes)) {
            return;
        }

        if ($create) {
            foreach ($qData['options'] as $oIdx => $opt) {
                [$optImagePath, $optAudioPath] = $this->resolveOptionFiles($opt);

                $question->options()->create([
                    'option_text' => $opt['option_text'] ?? '',
                    'is_correct'  => filter_var($opt['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'sort_order'  => ($oIdx + 1) * 10,
                    'dir'         => $opt['dir'] ?? 'ltr',
                    'image_path'  => $optImagePath,
                    'sound_path'  => $optAudioPath,
                    'font_size'   => isset($opt['font_size']) && $opt['font_size'] !== '' ? (int) $opt['font_size'] : null,
                ]);
            }
            return;
        }

        // Update path: preserve existing options, delete orphans
        $incomingIds    = collect($qData['options'])->pluck('id')->filter()->toArray();
        $existingOptions = $question->options()->get()->keyBy('id');

        $question->options()->whereNotIn('id', $incomingIds)->delete();

        foreach ($qData['options'] as $oIdx => $opt) {
            $existingOption = isset($opt['id']) ? $existingOptions->get($opt['id']) : null;

            [$optImagePath, $optAudioPath] = $this->resolveOptionFilesForUpdate($opt, $existingOption);

            $optData = [
                'option_text' => $opt['option_text'] ?? '',
                'is_correct'  => filter_var($opt['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort_order'  => ($oIdx + 1) * 10,
                'dir'         => $opt['dir'] ?? 'ltr',
                'image_path'  => $optImagePath,
                'sound_path'  => $optAudioPath,
                'font_size'   => isset($opt['font_size']) && $opt['font_size'] !== '' ? (int) $opt['font_size'] : null,
            ];

            if ($existingOption) {
                $existingOption->update($optData);
            } else {
                $question->options()->create($optData);
            }
        }
    }

    /** Resolve image/audio paths for a new option. */
    private function resolveOptionFiles(array $opt): array
    {
        $optImagePath = null;
        if (isset($opt['image']) && $opt['image'] instanceof \Illuminate\Http\UploadedFile) {
            $optImagePath = $opt['image']->store('options/images', 'public');
        }

        $optAudioPath = null;
        if (isset($opt['audio']) && $opt['audio'] instanceof \Illuminate\Http\UploadedFile) {
            $optAudioPath = $opt['audio']->store('options/audio', 'public');
        }

        return [$optImagePath, $optAudioPath];
    }

    /** Resolve image/audio paths for an updated option, respecting clear flags. */
    private function resolveOptionFilesForUpdate(array $opt, $existingOption): array
    {
        if (isset($opt['image']) && $opt['image'] instanceof \Illuminate\Http\UploadedFile) {
            $optImagePath = $opt['image']->store('options/images', 'public');
        } elseif (!empty($opt['clear_image'])) {
            $optImagePath = null;
        } else {
            $optImagePath = $existingOption?->image_path;
        }

        if (isset($opt['audio']) && $opt['audio'] instanceof \Illuminate\Http\UploadedFile) {
            $optAudioPath = $opt['audio']->store('options/audio', 'public');
        } elseif (!empty($opt['clear_audio'])) {
            $optAudioPath = null;
        } elseif (isset($opt['image']) && $opt['image'] instanceof \Illuminate\Http\UploadedFile) {
            // New image replaces old audio
            $optAudioPath = null;
        } else {
            $optAudioPath = $existingOption?->sound_path;
        }

        return [$optImagePath, $optAudioPath];
    }

    /** Duplicate all questions in a passage group. */
    private function duplicatePassageGroup(Question $question): Question
    {
        $passage    = $question->passage;
        $newPassage = $passage->replicate();
        $newPassage->save();

        $targetNewQuestion = null;
        $passageQuestions  = Question::where('passage_id', $passage->id)->get();

        foreach ($passageQuestions as $pQuestion) {
            $newPQuestion              = $pQuestion->replicate();
            $newPQuestion->passage_id  = $newPassage->id;
            $newPQuestion->created_by  = request()->user()?->id;
            $newPQuestion->updated_by  = request()->user()?->id;
            $newPQuestion->save();

            if ($pQuestion->options()->exists()) {
                $pQuestion->options()->each(function ($option) use ($newPQuestion) {
                    $newPQuestion->options()->create([
                        'option_text' => $option->option_text,
                        'is_correct'  => $option->is_correct,
                        'sort_order'  => $option->sort_order,
                    ]);
                });
            }

            if ($pQuestion->id === $question->id) {
                $targetNewQuestion = $newPQuestion;
            }
        }

        return $targetNewQuestion
            ?? Question::where('passage_id', $newPassage->id)->first();
    }

    /** Duplicate a standalone question. */
    private function duplicateStandalone(Question $question): Question
    {
        $newQuestion              = $question->replicate();
        $newQuestion->created_by  = request()->user()?->id;
        $newQuestion->updated_by  = request()->user()?->id;
        $newQuestion->save();

        if ($question->options()->exists()) {
            $question->options()->each(function ($option) use ($newQuestion) {
                $newQuestion->options()->create([
                    'option_text' => $option->option_text,
                    'is_correct'  => $option->is_correct,
                    'sort_order'  => $option->sort_order,
                    'dir'         => $option->dir ?? 'ltr',
                    'image_path'  => $option->image_path ?? null,
                ]);
            });
        }

        return $newQuestion;
    }
}
