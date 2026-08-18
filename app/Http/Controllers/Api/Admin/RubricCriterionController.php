<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RubricCriterion;
use App\Http\Requests\Admin\RubricCriterion\StoreRubricCriterionRequest;
use App\Http\Requests\Admin\RubricCriterion\UpdateRubricCriterionRequest;
use Database\Seeders\RubricCriterionSeeder;
use Illuminate\Http\Request;

class RubricCriterionController extends Controller
{
    /**
     * List all rubric criteria with category grouping and summary stats.
     */
    public function index(Request $request)
    {
        $skillType = $request->query('skill_type', 'writing');

        $criteria = RubricCriterion::where('skill_type', $skillType)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        // Auto-seed if table is empty
        if ($criteria->isEmpty()) {
            $seeder = new RubricCriterionSeeder();
            $seeder->run();
            $criteria = RubricCriterion::where('skill_type', $skillType)
                ->orderBy('order_index')
                ->orderBy('id')
                ->get();
        }

        $totalPoints = $criteria->where('is_active', true)->sum('max_points');
        $totalPercentage = $criteria->where('is_active', true)->sum('percentage');

        $categories = $criteria->groupBy('category')->map(function ($group, $categoryName) {
            return [
                'name'            => $categoryName,
                'total_points'    => $group->where('is_active', true)->sum('max_points'),
                'total_percentage'=> $group->where('is_active', true)->sum('percentage'),
                'criteria'        => $group->values(),
            ];
        })->values();

        return response()->json([
            'criteria'         => $criteria,
            'categories'       => $categories,
            'total_points'     => round($totalPoints, 2),
            'total_percentage' => round($totalPercentage, 2),
        ]);
    }

    /**
     * Get active rubric criteria for the Teacher Grading Desk.
     */
    public function active(Request $request)
    {
        $skillType = $request->query('skill_type', 'writing');

        $criteria = RubricCriterion::where('skill_type', $skillType)
            ->where('is_active', true)
            ->orderBy('order_index')
            ->orderBy('id')
            ->get();

        // Auto-seed if empty
        if ($criteria->isEmpty()) {
            $seeder = new RubricCriterionSeeder();
            $seeder->run();
            $criteria = RubricCriterion::where('skill_type', $skillType)
                ->where('is_active', true)
                ->orderBy('order_index')
                ->orderBy('id')
                ->get();
        }

        $categories = $criteria->groupBy('category')->map(function ($group, $categoryName) {
            return [
                'category'        => $categoryName,
                'total_points'    => $group->sum('max_points'),
                'total_percentage'=> $group->sum('percentage'),
                'items'           => $group->values(),
            ];
        })->values();

        return response()->json([
            'criteria'   => $criteria,
            'categories' => $categories,
            'max_total'  => $criteria->sum('max_points'),
        ]);
    }

    /**
     * Create a new rubric criterion.
     */
    public function store(StoreRubricCriterionRequest $request)
    {
        $validated = $request->validated();

        $validated['skill_type'] = $validated['skill_type'] ?? 'writing';
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['order_index'] = $validated['order_index'] ?? (RubricCriterion::max('order_index') + 1);

        $criterion = RubricCriterion::create($validated);

        return response()->json([
            'message'   => 'Rubric criterion created successfully.',
            'criterion' => $criterion,
        ], 201);
    }

    /**
     * Update an existing rubric criterion.
     */
    public function update(UpdateRubricCriterionRequest $request, RubricCriterion $rubric)
    {
        $validated = $request->validated();

        $rubric->update($validated);

        return response()->json([
            'message'   => 'Rubric criterion updated successfully.',
            'criterion' => $rubric,
        ]);
    }

    /**
     * Delete a rubric criterion.
     */
    public function destroy(RubricCriterion $rubric)
    {
        $rubric->delete();

        return response()->json([
            'message' => 'Rubric criterion deleted successfully.',
        ]);
    }

    /**
     * Reset criteria to standard default (14 criteria).
     */
    public function resetDefault(Request $request)
    {
        $seeder = new RubricCriterionSeeder();
        $seeder->run();

        return response()->json([
            'message' => 'Rubric criteria have been reset to the standard default successfully.',
        ]);
    }
}
