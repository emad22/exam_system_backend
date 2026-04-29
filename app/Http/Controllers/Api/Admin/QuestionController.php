<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\Question;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    /**
     * Get all questions with skill info
     */
    public function index(Request $request)
    {
        $query = Question::with(['skill', 'options', 'passage', 'level', 'exam:id,title']);

        if ($request->has('skill_id')) {
            $query->where('skill_id', $request->skill_id);
        }

        if ($request->has('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('exam_id');
        }

        return response()->json($query->latest()->paginate(50));
    }

    /**
     * Store new Question with Options, Passage handling, and Level mapping.
     */
    public function store(Request $request)
    {
        $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'exam_id' => 'required|exists:exams,id',
            'level_id' => 'required|integer|min:1|max:9',

            // Passage Logic
            'passage_mode' => 'required|in:none,existing,new',
            'passage_id' => 'required_if:passage_mode,existing|exists:passages,id|nullable',
            'passage_type' => 'required_if:passage_mode,new|in:text,image,audio,video|nullable',
            'passage_title' => 'nullable|string',
            'passage_content' => 'nullable|string',
            'passage_questions_limit' => 'nullable|integer|min:1',
            'passage_is_random' => 'nullable',
            'p_media_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a,jpeg,png,jpg,gif,svg,mp4,webm|max:10240',
            'p_audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:10240',
            'p_image_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:10240',

            // Questions Batch
            'questions' => 'required|array|min:1',
            'questions.*.type' => 'required|in:mcq,true_false,short_answer,writing,speaking,upload,drag_drop,word_selection,fill_blank,matching,ordering,highlight,listening,click_word',
            'questions.*.content' => 'nullable|string',  // nullable: content may be empty when question IS a media file
            'questions.*.instructions' => 'nullable|string',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.sort_order' => 'nullable|integer',
            'questions.*.options' => 'nullable|array',
        ]);

        // Logic check for MCQ/TrueFalse for all questions in the batch
        foreach ($request->questions as $index => $qData) {
            if (in_array($qData['type'], ['mcq', 'true_false', 'drag_drop', 'word_selection', 'click_word', 'fill_blank', 'matching', 'ordering', 'highlight', 'listening'])) {
                if (!isset($qData['options']) || count($qData['options']) < 2) {
                    return response()->json(['message' => "Options are required for question #".($index+1)], 422);
                }
                $hasCorrect = collect($qData['options'])->contains('is_correct', true);
                if (!$hasCorrect) {
                    return response()->json(['message' => "You must select a correct answer for question #".($index+1)], 422);
                }
            }
        }

        return DB::transaction(function () use ($request) {
            $passageId = null;

            // 1. Handle Passage (Shared for the whole batch)
            if ($request->passage_mode === 'existing') {
                $passageId = $request->passage_id;
            } elseif ($request->passage_mode === 'new') {
                $pMediaPath = null;
                $pAudioPath = null;
                $pImagePath = null;
                if ($request->hasFile('p_media_file')) {
                    $pMediaPath = $request->file('p_media_file')->store('passages', 'public');
                }
                if ($request->hasFile('p_audio_file')) {
                    $pAudioPath = $request->file('p_audio_file')->store('passages/audio', 'public');
                }
                if ($request->hasFile('p_image_file')) {
                    $pImagePath = $request->file('p_image_file')->store('passages/images', 'public');
                }
 
                $passage = \App\Models\Passage::create([
                    'type' => $request->passage_type,
                    'title' => $request->passage_title,
                    'content' => $request->passage_content,
                    'media_path' => $pMediaPath,
                    'audio_path' => $pAudioPath,
                    'image_path' => $pImagePath,
                    'questions_limit' => $request->passage_questions_limit,
                    'is_random' => $request->boolean('passage_is_random'),
                ]);
                $passageId = $passage->id;
            }

            // 2. Map Slider Level to Level ID (or dynamically create it if missing)
            $level = Level::firstOrCreate(
                [
                    'skill_id' => $request->skill_id,
                    'level_number' => $request->level_id
                ],
                [
                    'default_standalone_quantity' => 0,
                    'default_passage_quantity' => 0,
                    'default_question_count' => 0
                ]
            );
            $actualLevelId = $level->id;

            $createdQuestions = [];

            // 3. Process each question in the batch
            foreach ($request->questions as $index => $qData) {
                $qMediaPath = null;
                $qAudioPath = null;
                $qImagePath = null;

                $fileKey = "questions.{$index}.q_media_file";
                if ($request->hasFile($fileKey)) {
                    $qMediaPath = $request->file($fileKey)->store('questions', 'public');
                }

                $audioKey = "questions.{$index}.q_audio_file";
                if ($request->hasFile($audioKey)) {
                    $qAudioPath = $request->file($audioKey)->store('questions/audio', 'public');
                }

                $imageKey = "questions.{$index}.q_image_file";
                if ($request->hasFile($imageKey)) {
                    $qImagePath = $request->file($imageKey)->store('questions/images', 'public');
                }

                $question = Question::create([
                    'skill_id' => $request->skill_id,
                    'exam_id' => $request->exam_id,
                    'level_id' => $actualLevelId,
                    'passage_id' => $passageId,
                    'type' => $qData['type'],
                    'instructions' => $qData['instructions'] ?? null,
                    'content' => $qData['content'] ?? '',
                    'media_path' => $qMediaPath,
                    'audio_path' => $qAudioPath,
                    'image_path' => $qImagePath,
                    'points' => $qData['points'] ?? 1,
                    'sort_order' => $qData['sort_order'] ?? 0,
                    'min_words' => $qData['min_words'] ?? null,
                    'max_words' => $qData['max_words'] ?? null,
                ]);

                // 4. Create Options
                if (!empty($qData['options']) && !in_array($qData['type'], ['writing', 'speaking', 'upload'])) {
                    foreach ($qData['options'] as $opt) {
                        $question->options()->create([
                            'option_text' => $opt['option_text'] ?? '',
                            'is_correct' => filter_var($opt['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN)
                        ]);
                    }
                }

                $createdQuestions[] = $question->id;
            }

            return response()->json([
                'message' => count($createdQuestions) . ' questions and passage created successfully.',
                'question_ids' => $createdQuestions,
                'passage_id' => $passageId
            ], 201);
        });
    }

    /**
     * Get a single question with its options and full passage context if available
     */
    public function show(Question $question)
    {
        return response()->json($question->load(['options', 'skill', 'passage.questions.options', 'level']));
    }

    /**
     * Update Questions (Batch support)
     */
    public function update(Request $request, Question $question)
    {
        $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'exam_id' => 'required|exists:exams,id',
            'level_id' => 'required|integer|min:1|max:9',

            // Passage Logic
            'passage_mode' => 'required|in:none,existing,new',
            'passage_id' => 'required_if:passage_mode,existing|exists:passages,id|nullable',
            'passage_type' => 'required_if:passage_mode,new|in:text,image,audio,video|nullable',
            'passage_title' => 'nullable|string',
            'passage_content' => 'nullable|string',
            'passage_questions_limit' => 'nullable|integer|min:1',
            'passage_is_random' => 'nullable',
            'p_media_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a,jpeg,png,jpg,gif,svg,mp4,webm|max:10240',
            'p_audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:10240',
            'p_image_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:10240',

            // Questions Batch
            'questions' => 'nullable|array',
            'questions.*.id' => 'nullable|exists:questions,id',
            'questions.*.type' => 'required|in:mcq,true_false,short_answer,writing,speaking,upload,drag_drop,word_selection,fill_blank,matching,ordering,highlight,listening,click_word',
            'questions.*.content' => 'nullable|string',  // nullable: content may be empty when question IS a media file
            'questions.*.instructions' => 'nullable|string',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.sort_order' => 'nullable|integer',
            'questions.*.options' => 'nullable|array',
        ]);

        return DB::transaction(function () use ($request, $question) {
            $passageId = $question->passage_id;

            // 1. Handle Passage Update
            if ($request->passage_mode === 'none') {
                $passageId = null;

            } elseif ($request->passage_mode === 'existing') {
                // Link to selected passage
                $passageId = $request->passage_id;

                // If the selected passage is the SAME as the question's current passage,
                // also update its content fields (user may have edited them)
                if ($passageId && $question->passage_id == $passageId && $question->passage) {
                    $pMediaPath = $request->hasFile('p_media_file') ? $request->file('p_media_file')->store('passages', 'public') : $question->passage->media_path;
                    $pAudioPath = $request->hasFile('p_audio_file') ? $request->file('p_audio_file')->store('passages/audio', 'public') : $question->passage->audio_path;
                    $pImagePath = $request->hasFile('p_image_file') ? $request->file('p_image_file')->store('passages/images', 'public') : $question->passage->image_path;

                    $question->passage->update([
                        'type'            => $request->passage_type ?? $question->passage->type,
                        'title'           => $request->passage_title ?? $question->passage->title,
                        'content'         => $request->passage_content ?? $question->passage->content,
                        'media_path'      => $pMediaPath,
                        'audio_path'      => $pAudioPath,
                        'image_path'      => $pImagePath,
                        'questions_limit' => $request->passage_questions_limit ?? $question->passage->questions_limit,
                        'is_random'       => $request->boolean('passage_is_random'),
                    ]);
                }

            } elseif ($request->passage_mode === 'new') {
                $pMediaPath = null;
                if ($request->hasFile('p_media_file')) {
                    $pMediaPath = $request->file('p_media_file')->store('passages', 'public');
                }
                $passage = \App\Models\Passage::create([
                    'type'            => $request->passage_type,
                    'title'           => $request->passage_title,
                    'content'         => $request->passage_content,
                    'media_path'      => $pMediaPath,
                    'questions_limit' => $request->passage_questions_limit,
                    'is_random'       => $request->boolean('passage_is_random'),
                ]);
                $passageId = $passage->id;
            }

            // 2. Map Level (or dynamically create it if missing)
            $level = \App\Models\Level::firstOrCreate(
                [
                    'skill_id' => $request->skill_id,
                    'level_number' => $request->level_id
                ],
                [
                    'default_standalone_quantity' => 0,
                    'default_passage_quantity' => 0,
                    'default_question_count' => 0
                ]
            );
            $actualLevelId = $level->id;

            // 3. Process Batch if provided, else single update
            $questionsData = $request->questions ?? [
                array_merge($request->only(['type', 'content', 'instructions', 'points', 'min_words', 'max_words', 'options']), ['id' => $question->id])
            ];

            foreach ($questionsData as $index => $qData) {
                $qMediaPath = null;
                $qAudioPath = null;
                $qImagePath = null;
                $fileKey = "questions.{$index}.q_media_file";
                $audioKey = "questions.{$index}.q_audio_file";
                $imageKey = "questions.{$index}.q_image_file";
                
                // Single update handling
                if (count($questionsData) === 1) {
                    if (!$request->hasFile($fileKey) && $request->hasFile('q_media_file')) $fileKey = 'q_media_file';
                    if (!$request->hasFile($audioKey) && $request->hasFile('q_audio_file')) $audioKey = 'q_audio_file';
                    if (!$request->hasFile($imageKey) && $request->hasFile('q_image_file')) $imageKey = 'q_image_file';
                }

                if ($request->hasFile($fileKey)) {
                    $qMediaPath = $request->file($fileKey)->store('questions', 'public');
                }
                if ($request->hasFile($audioKey)) {
                    $qAudioPath = $request->file($audioKey)->store('questions/audio', 'public');
                }
                if ($request->hasFile($imageKey)) {
                    $qImagePath = $request->file($imageKey)->store('questions/images', 'public');
                }

                $qInstance = isset($qData['id']) ? Question::find($qData['id']) : new Question();
                
                $data = [
                    'skill_id' => $request->skill_id,
                    'exam_id' => $request->exam_id,
                    'level_id' => $actualLevelId,
                    'passage_id' => $passageId,
                    'type' => $qData['type'],
                    'instructions' => $qData['instructions'] ?? null,
                    'content' => $qData['content'] ?? '',
                    'points' => $qData['points'] ?? 1,
                    'sort_order' => $qData['sort_order'] ?? 0,
                    'min_words' => $qData['min_words'] ?? null,
                    'max_words' => $qData['max_words'] ?? null,
                ];

                if ($qMediaPath) $data['media_path'] = $qMediaPath;
                if ($qAudioPath) $data['audio_path'] = $qAudioPath;
                if ($qImagePath) $data['image_path'] = $qImagePath;

                $qInstance->fill($data);
                $qInstance->save();

                // 4. Update Options
                if (isset($qData['options']) && !in_array($qData['type'], ['writing', 'speaking', 'upload'])) {
                    $qInstance->options()->delete();
                    foreach ($qData['options'] as $opt) {
                        $qInstance->options()->create([
                            'option_text' => $opt['option_text'] ?? '',
                            'is_correct' => filter_var($opt['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN)
                        ]);
                    }
                }

            }

            return response()->json([
                'message' => 'Batch updated successfully.',
                'question' => $question->fresh(['options', 'passage.questions.options'])
            ]);
        });
    }

    /**
     * Delete a question and its options
     */
    public function destroy(Question $question)
    {
        $question->options()->delete();
        $question->delete();
        return response()->json(['message' => 'Question deleted successfully.']);
    }

    /**
     * Get all questions for a specific skill
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
     * Bulk update difficulty level for multiple questions
     */
    public function bulkUpdateLevel(Request $request)
    {
        $validated = $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => 'exists:questions,id',
            'level_id' => 'required|integer|min:0|max:9',
        ]);

        Question::whereIn('id', $validated['question_ids'])
            ->update(['level_id' => $validated['level_id']]);

        return response()->json([
            'message' => 'Questions updated successfully.'
        ]);
    }
    /**
     * Get unique tags for questions belonging to a specific skill
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
     * Standalone media upload for Exam Constructor
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:mp3,wav,ogg,m4a,jpeg,png,jpg,gif,svg,mp4,webm|max:10240',
        ]);

        $path = $request->file('file')->store('questions', 'public');

        return response()->json([
            'path' => $path,
            'url' => asset('storage/' . $path)
        ]);
    }
}
