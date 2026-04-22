<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Passage;
use Illuminate\Http\Request;

class PassageController extends Controller
{
    /**
     * Display a listing of the passages.
     */
    public function index(Request $request)
    {
        $query = Passage::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Default to returning all for the question creation dropdown
        if (!$request->has('per_page')) {
            return response()->json($query->latest()->get());
        }

        return response()->json($query->latest()->paginate($request->get('per_page', 20)));
    }

    /**
     * Store a newly created passage in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:text,image,audio,video',
            'title' => 'nullable|string|max:255',
            'content' => 'required_if:type,text|nullable|string',
            'media_path' => 'nullable|string',
            'questions_limit' => 'nullable|integer',
            'is_random' => 'nullable|boolean'
        ]);

        $passage = Passage::create($validated);

        return response()->json([
            'message' => 'Passage created successfully',
            'passage' => $passage
        ], 201);
    }

    /**
     * Display the specified passage.
     */
    public function show(Passage $passage)
    {
        return response()->json($passage->load('questions'));
    }

    /**
     * Update the specified passage in storage.
     */
    public function update(Request $request, Passage $passage)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:text,image,audio,video',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'media_path' => 'nullable|string',
            'questions_limit' => 'nullable|integer',
            'is_random' => 'nullable|boolean'
        ]);

        $passage->update($validated);

        return response()->json([
            'message' => 'Passage updated successfully',
            'passage' => $passage
        ]);
    }

    /**
     * Remove the specified passage from storage.
     */
    public function destroy(Passage $passage)
    {
        $passage->delete();
        return response()->json(['message' => 'Passage deleted successfully']);
    }
}
