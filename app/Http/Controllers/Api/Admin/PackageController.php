<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;

class PackageController extends Controller
{
    /**
     * Get all packages
     */
    public function index()
    {
        return response()->json(Package::with('exam')->orderBy('skills_count')->get());
    }

    /**
     * Store new package
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'skills_count' => 'required|integer|min:1|max:5',
            'description' => 'nullable|string',
            'wp_package_id' => 'nullable|string|max:255',
            'exam_id' => 'nullable|exists:exams,id',
            'skills' => 'nullable|array',
            'skills.*' => 'integer|exists:skills,id',
        ]);

        $package = Package::create($validated);

        return response()->json($package, 201);
    }

    /**
     * Get single package
     */
    public function show(Package $package)
    {
        return response()->json($package);
    }

    /**
     * Update package
     */
    public function update(\Illuminate\Http\Request $request, Package $package)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'skills_count' => 'required|integer|min:1|max:5',
            'description' => 'nullable|string',
            'wp_package_id' => 'nullable|string|max:255',
            'exam_id' => 'nullable|exists:exams,id',
            'skills' => 'nullable|array',
            'skills.*' => 'integer|exists:skills,id',
        ]);

        $package->update($validated);

        return response()->json($package);
    }

    /**
     * Delete package
     */
    public function destroy(Package $package)
    {
        $package->delete();
        return response()->json(null, 204);
    }
}
