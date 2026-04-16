<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExamCategoryController extends Controller
{
    /**
     * List all categories
     */
    public function index()
    {
        return response()->json(ExamCategory::withCount('exams')->get());
    }

    /**
     * Store a new category
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:exam_categories,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category = ExamCategory::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Assessment category created successfully.',
            'category' => $category
        ], 201);
    }

    /**
     * Update existing category
     */
    public function update(Request $request, ExamCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:exam_categories,name,' . $category->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? $category->description,
            'is_active' => $validated['is_active'] ?? $category->is_active,
        ]);

        return response()->json([
            'message' => 'Category updated successfully.',
            'category' => $category
        ]);
    }

    /**
     * Delete category
     */
    public function destroy(ExamCategory $category)
    {
        if ($category->exams()->count() > 0) {
            return response()->json(['message' => 'Cannot delete category while exams are assigned to it.'], 422);
        }

        $category->delete();
        return response()->json(['message' => 'Category removed successfully.']);
    }
}
