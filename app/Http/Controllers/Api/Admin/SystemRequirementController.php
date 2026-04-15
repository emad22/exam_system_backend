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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string',
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
            'order' => 'integer',
        ]);

        return SystemRequirement::create($validated);
    }

    /**
     * Update requirement
     */
    public function update(Request $request, SystemRequirement $systemRequirement)
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'string',
            'category' => 'nullable|string',
            'is_active' => 'boolean',
            'is_mandatory' => 'boolean',
            'order' => 'integer',
        ]);

        $systemRequirement->update($validated);
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
