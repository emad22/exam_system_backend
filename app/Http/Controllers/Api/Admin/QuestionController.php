<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Skill;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * Get all questions with skill info
     */
    public function index(Request $request)
    {
        $query = Question::with(['skill', 'options'])->withCount('options');

        if ($request->has('skill_id')) {
            $query->where('skill_id', $request->skill_id);
        }

        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->difficulty_level);
        }

        return response()->json($query->latest()->paginate(50));
    }

    /**
     * Store new Question with Options (Phase 7)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'type' => 'required|in:mcq,true_false,short_answer,writing,speaking',
            'content' => 'required|string',
            'difficulty_level' => 'required|integer|min:1|max:9',
            'points' => 'required|integer|min:1',
            'media_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:10240', // 10MB limit
            'options' => 'nullable|array',
            'options.*.option_text' => 'required_with:options|string',
            'options.*.is_correct' => 'required_with:options|boolean',
            'passage_content' => 'nullable|string',
            'passage_group_id' => 'nullable|string',
            'passage_randomize' => 'boolean',
            'passage_limit' => 'nullable|integer|min:1',
            'min_words' => 'nullable|integer|min:0',
            'max_words' => 'nullable|integer|min:0',
        ]);

        $question = Question::create([
            'skill_id' => $validated['skill_id'],
            'group_tag' => $request->group_tag,
            'type' => $validated['type'],
            'content' => $validated['content'],
            'difficulty_level' => $validated['difficulty_level'],
            'points' => $validated['points'],
            'passage_content' => $validated['passage_content'] ?? null,
            'passage_group_id' => $validated['passage_group_id'] ?? null,
            'passage_randomize' => $validated['passage_randomize'] ?? true,
            'passage_limit' => $validated['passage_limit'] ?? null,
            'min_words' => $validated['min_words'] ?? null,
            'max_words' => $validated['max_words'] ?? null,
            'media_path' => $request->hasFile('media_file') ? $request->file('media_file')->store('questions', 'public') : null,
        ]);

        if (isset($validated['options']) && is_array($validated['options'])) {
            foreach ($validated['options'] as $option) {
                $question->options()->create($option);
            }
        }

        return response()->json([
            'message' => 'Question created successfully with options.',
            'question' => $question->load('options')
        ], 201);
    }

    /**
     * Get a single question with its options
     */
    public function show(Question $question)
    {
        return response()->json($question->load(['options', 'skill']));
    }

    /**
     * Update an existing question and its options
     */
    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'skill_id'         => 'required|exists:skills,id',
            'type'             => 'required|in:mcq,true_false,short_answer,writing,speaking',
            'content'          => 'required|string',
            'difficulty_level' => 'required|integer|min:1|max:9',
            'points'           => 'required|integer|min:1',
            'group_tag'        => 'nullable|string|max:255',
            'options'          => 'nullable|array',
            'options.*.option_text' => 'required_with:options|string',
            'options.*.is_correct'  => 'required_with:options|boolean',
            'passage_content'       => 'nullable|string',
            'passage_group_id'      => 'nullable|string',
            'passage_randomize'     => 'nullable|boolean',
            'passage_limit'         => 'nullable|integer|min:1',
            'min_words'             => 'nullable|integer|min:0',
            'max_words'             => 'nullable|integer|min:0',
            'media_file'            => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:10240',
        ]);

        $question->update([
            'skill_id'         => $validated['skill_id'],
            'type'             => $validated['type'],
            'content'          => $validated['content'],
            'difficulty_level' => $validated['difficulty_level'],
            'points'           => $validated['points'],
            'group_tag'        => $validated['group_tag'] ?? $question->group_tag,
            'passage_content'  => $validated['passage_content'] ?? $question->passage_content,
            'passage_group_id' => $validated['passage_group_id'] ?? $question->passage_group_id,
            'passage_randomize'=> $validated['passage_randomize'] ?? $question->passage_randomize,
            'passage_limit'    => $validated['passage_limit'] ?? $question->passage_limit,
            'min_words'        => $validated['min_words'] ?? $question->min_words,
            'max_words'        => $validated['max_words'] ?? $question->max_words,
        ]);

        if ($request->hasFile('media_file')) {
            $question->update([
                'media_path' => $request->file('media_file')->store('questions', 'public')
            ]);
        }

        // Replace all options
        if (isset($validated['options'])) {
            $question->options()->delete();
            foreach ($validated['options'] as $opt) {
                $question->options()->create([
                    'option_text' => $opt['option_text'],
                    'is_correct'  => $opt['is_correct'],
                ]);
            }
        }

        return response()->json([
            'message'  => 'Question updated successfully.',
            'question' => $question->load('options')
        ]);
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
            'difficulty_level' => 'required|integer|min:0|max:9',
        ]);

        Question::whereIn('id', $validated['question_ids'])
            ->update(['difficulty_level' => $validated['difficulty_level']]);

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
            'file' => 'required|file|mimes:mp3,wav,ogg,m4a|max:10240',
        ]);

        $path = $request->file('file')->store('questions', 'public');
        
        return response()->json([
            'path' => $path,
            'url'  => asset('storage/' . $path)
        ]);
    }
}
