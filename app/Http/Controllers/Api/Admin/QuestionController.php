<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
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
        $query = Question::with(['skill', 'options', 'passage', 'level', 'exams:id,title'])->withCount('exams');

        if ($request->has('skill_id')) {
            $query->where('skill_id', $request->skill_id);
        }

        if ($request->has('level_id')) {
            $query->where('level_id', $request->level_id);
        }

        if ($request->boolean('unassigned')) {
            $query->doesntHave('exams');
        }

        return response()->json($query->latest()->paginate(50));
    }

    /**
     * Store new Question with Options, Passage handling, and Level mapping.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'exam_id' => 'nullable|exists:exams,id',
            'level_id' => 'required|integer|min:1|max:9',
            'type' => 'required|in:mcq,true_false,short_answer,writing,speaking,upload',
            'content' => 'required_unless:type,upload|string|nullable',
            'instructions' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'min_words' => 'nullable|integer|min:0',
            'max_words' => 'nullable|integer|min:0',

            // Question Media
            'q_media_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a,jpeg,png,jpg,gif,svg,mp4,webm|max:10240',

            // Passage Logic
            'passage_mode' => 'required|in:none,existing,new',
            'passage_id' => 'required_if:passage_mode,existing|exists:passages,id|nullable',
            'passage_type' => 'required_if:passage_mode,new|in:text,image,audio,video|nullable',
            'passage_title' => 'nullable|string',
            'passage_content' => 'nullable|string',
            'passage_questions_limit' => 'nullable|integer|min:1',
            'passage_is_random' => 'nullable|boolean',
            'p_media_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a,jpeg,png,jpg,gif,svg,mp4,webm|max:10240',

            // Options
            'options' => 'nullable|array',
            'options.*.option_text' => 'nullable|string',
            'options.*.is_correct' => 'nullable|boolean',
        ]);

        // Logic check for MCQ/TrueFalse
        if (in_array($request->type, ['mcq', 'true_false'])) {
            if (!$request->options || count($request->options) < 2) {
                return response()->json(['message' => 'Options are required for this question type.'], 422);
            }
            $hasCorrect = collect($request->options)->contains('is_correct', true);
            if (!$hasCorrect) {
                return response()->json(['message' => 'You must select a correct answer.'], 422);
            }
        }

        return DB::transaction(function () use ($request, $validated) {
            $passageId = null;

            // 1. Handle Passage
            if ($request->passage_mode === 'existing') {
                $passageId = $request->passage_id;
            } elseif ($request->passage_mode === 'new') {
                $pMediaPath = null;
                if ($request->hasFile('p_media_file')) {
                    $pMediaPath = $request->file('p_media_file')->store('passages', 'public');
                }

                $passage = \App\Models\Passage::create([
                    'type' => $request->passage_type,
                    'title' => $request->passage_title,
                    'content' => $request->passage_content,
                    'media_path' => $pMediaPath,
                    'questions_limit' => $request->passage_questions_limit,
                    'is_random' => $request->boolean('passage_is_random'),
                ]);
                $passageId = $passage->id;
            }

            // 2. Map Slider Level to Level ID
            $actualLevelId = \App\Models\Level::where('skill_id', $request->skill_id)
                ->where('level_number', $request->level_id)
                ->value('id') ?? $request->level_id; // Fallback to provided number if mapping fails

            // 3. Create Question
            $qMediaPath = null;
            if ($request->hasFile('q_media_file')) {
                $qMediaPath = $request->file('q_media_file')->store('questions', 'public');
            }

            $question = Question::create([
                'skill_id' => $request->skill_id,
                'exam_id' => $request->exam_id,
                'level_id' => $actualLevelId,
                'passage_id' => $passageId,
                'type' => $request->type,
                'instructions' => $request->instructions,
                'content' => $request->content ?? '',
                'media_path' => $qMediaPath,
                'points' => $request->points,
                'min_words' => $request->min_words,
                'max_words' => $request->max_words,
            ]);

            // 4. Create Options
            if (!empty($request->options) && !in_array($request->type, ['writing', 'speaking', 'upload'])) {
                foreach ($request->options as $opt) {
                    $question->options()->create([
                        'option_text' => $opt['option_text'] ?? '',
                        'is_correct' => filter_var($opt['is_correct'], FILTER_VALIDATE_BOOLEAN)
                    ]);
                }
            }

            // 5. Link to Exam pivot if exam_id provided
            if ($request->exam_id) {
                $question->exams()->syncWithoutDetaching([$request->exam_id]);
            }

            return response()->json([
                'message' => 'Question and passage created successfully.',
                'question' => $question->load(['options', 'passage'])
            ], 201);
        });
    }

    /**
     * Get a single question with its options
     */
    public function show(Question $question)
    {
        return response()->json($question->load(['options', 'skill', 'passage']));
    }

    /**
     * Update an existing question and its options
     */
    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'exam_id' => 'nullable|exists:exams,id',
            'level_id' => 'required|integer|min:1|max:9',
            'type' => 'required|in:mcq,true_false,short_answer,writing,speaking,upload',
            'content' => 'required_unless:type,upload|string|nullable',
            'instructions' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'min_words' => 'nullable|integer|min:0',
            'max_words' => 'nullable|integer|min:0',

            // Question Media
            'q_media_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a,jpeg,png,jpg,gif,svg,mp4,webm|max:10240',

            // Passage Logic (Updates are tricky, usually either keep same or change ID)
            'passage_mode' => 'required|in:none,existing,new',
            'passage_id' => 'required_if:passage_mode,existing|exists:passages,id|nullable',
            
            // Options
            'options' => 'nullable|array',
            'options.*.option_text' => 'nullable|string',
            'options.*.is_correct' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($request, $validated, $question) {
            $passageId = $question->passage_id;

            if ($request->passage_mode === 'none') {
                $passageId = null;
            } elseif ($request->passage_mode === 'existing') {
                $passageId = $request->passage_id;
            }
            // Note: We don't handle "create new passage during update" here to keep it simple, 
            // usually you'd link to an existing or none.

            $actualLevelId = \App\Models\Level::where('skill_id', $request->skill_id)
                ->where('level_number', $request->level_id)
                ->value('id') ?? $request->level_id;

            $updateData = [
                'skill_id' => $request->skill_id,
                'exam_id' => $request->exam_id,
                'level_id' => $actualLevelId,
                'passage_id' => $passageId,
                'type' => $request->type,
                'instructions' => $request->instructions,
                'content' => $request->content ?? '',
                'points' => $request->points,
                'min_words' => $request->min_words,
                'max_words' => $request->max_words,
            ];

            if ($request->hasFile('q_media_file')) {
                $updateData['media_path'] = $request->file('q_media_file')->store('questions', 'public');
            }

            $question->update($updateData);

            // Sync Options
            if (isset($request->options) && !in_array($request->type, ['writing', 'speaking', 'upload'])) {
                $question->options()->delete();
                foreach ($request->options as $opt) {
                    $question->options()->create([
                        'option_text' => $opt['option_text'] ?? '',
                        'is_correct' => filter_var($opt['is_correct'], FILTER_VALIDATE_BOOLEAN)
                    ]);
                }
            }

            // Update Exam pivot if exam_id provided
            if ($request->exam_id) {
                $question->exams()->syncWithoutDetaching([$request->exam_id]);
            }

            return response()->json([
                'message' => 'Question updated successfully.',
                'question' => $question->load(['options', 'passage'])
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
