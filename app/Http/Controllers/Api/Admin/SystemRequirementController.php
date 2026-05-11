<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemRequirement;
use Illuminate\Http\Request;

class SystemRequirementController extends Controller
{
    /**
     * Admin: List all requirements
     */
    public function index()
    {
        return SystemRequirement::orderBy('order')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Student/Public: List active requirements
     */
    public function activeList()
    {
        return SystemRequirement::where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * Store new requirement
     */
    public function store(Request $request)
    {
        $data = $request->all();
        if (isset($data['test_config']) && is_string($data['test_config'])) {
            $data['test_config'] = json_decode($data['test_config'], true);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'test_type' => 'nullable|string|in:none,audio_output,audio_input,video_input,network_speed,browser_compatibility',
            'test_config' => 'nullable|array',
            'category' => 'nullable|string',
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
            'order' => 'integer',
            'audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $testConfig = $validated['test_config'] ?? [];

        if ($request->hasFile('audio_file')) {
            $path = $request->file('audio_file')->store('requirements/audio', 'public');
            $testConfig['audio_url'] = asset('storage/' . $path);
        }

        $validated['test_config'] = $testConfig;

        return SystemRequirement::create($validated);
    }

    /**
     * Update requirement
     */
    public function update(Request $request, SystemRequirement $systemRequirement)
    {
        $data = $request->all();
        if (isset($data['test_config']) && is_string($data['test_config'])) {
            $data['test_config'] = json_decode($data['test_config'], true);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'title' => 'string|max:255',
            'description' => 'string',
            'test_type' => 'nullable|string|in:none,audio_output,audio_input,video_input,network_speed,browser_compatibility',
            'test_config' => 'nullable|array',
            'category' => 'nullable|string',
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
            'order' => 'integer',
            'audio_file' => 'nullable|file|mimes:mp3,wav,ogg,m4a|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $testConfig = $validated['test_config'] ?? $systemRequirement->test_config ?? [];

        if ($request->hasFile('audio_file')) {
            $path = $request->file('audio_file')->store('requirements/audio', 'public');
            $testConfig['audio_url'] = asset('storage/' . $path);
        }

        $validated['test_config'] = $testConfig;

        $systemRequirement->update($validated);
        return $systemRequirement;
    }

    /**
     * Show requirement details
     */
    public function show(SystemRequirement $systemRequirement)
    {
        return $systemRequirement;
    }

    /**
     * Delete requirement
     */
    public function destroy(SystemRequirement $systemRequirement)
    {
        $systemRequirement->delete();
        return response()->json(['message' => 'Requirement deleted']);
    }
}
