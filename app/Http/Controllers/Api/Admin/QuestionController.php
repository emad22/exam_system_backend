<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * Get all questions with skill info
     */
    public function index()
    {
        return response()->json(Question::with('skill')->withCount('options')->latest()->paginate(30));
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
            'options' => 'required_if:type,mcq,true_false,short_answer|array',
            'options.*.option_text' => 'required_with:options|string',
            'options.*.is_correct' => 'required_with:options|boolean',
        ]);

        $question = Question::create([
            'skill_id' => $validated['skill_id'],
            'group_tag' => $request->group_tag,
            'type' => $validated['type'],
            'content' => $validated['content'],
            'difficulty_level' => $validated['difficulty_level'],
            'points' => $validated['points'],
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
        ]);

        $question->update([
            'skill_id'         => $validated['skill_id'],
            'type'             => $validated['type'],
            'content'          => $validated['content'],
            'difficulty_level' => $validated['difficulty_level'],
            'points'           => $validated['points'],
            'group_tag'        => $validated['group_tag'] ?? $question->group_tag,
        ]);

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
}
