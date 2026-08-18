<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Level\StoreLevelRequest;
use App\Http\Requests\Admin\Level\UpdateLevelRequest;
use App\Models\Level;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;

class LevelController extends Controller
{
    /**
     * Display a listing of levels
     */
    public function index(Request $request)
    {
        $query = Level::query();
        if ($request->has('skill_id')) {
            $query->where('skill_id', $request->skill_id);
        }
        return response()->json($query->orderBy('skill_id')->orderBy('level_number')->get());
    }

    /**
     * Store a new level for a skill
     */
    public function store(StoreLevelRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('instructions_audio')) {
            $path = $request->file('instructions_audio')->store('levels/audio', 'public');
            $validated['instructions_audio'] = $path;
        }

        $level = Level::create($validated);

        return response()->json([
            'message' => 'Level created successfully',
            'level' => $level
        ], 201);
    }

    /**
     * Get a single level
     */
    public function show(Level $level)
    {
        return response()->json($level);
    }

    /**
     * Update an existing level
     */
    public function update(UpdateLevelRequest $request, Level $level)
    {
        $validated = $request->validated();

        if ($request->hasFile('instructions_audio')) {
            // Delete old audio if exists
            if ($level->instructions_audio) {
                Storage::disk('public')->delete($level->instructions_audio);
            }
            $path = $request->file('instructions_audio')->store('levels/audio', 'public');
            $validated['instructions_audio'] = $path;
        }

        $level->update($validated);

        return response()->json([
            'message' => 'Level updated successfully',
            'level' => $level
        ]);
    }

    /**
     * Delete a level
     */
    public function destroy(Level $level)
    {
        if ($level->instructions_audio) {
            Storage::disk('public')->delete($level->instructions_audio);
        }
        $level->delete();

        return response()->json([
            'message' => 'Level deleted successfully'
        ]);
    }
}
