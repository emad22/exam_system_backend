<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CefrActflThreshold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CefrActflThresholdController extends Controller
{
    /**
     * GET /admin/cefr-actfl-thresholds
     *
     * Returns all thresholds grouped by skill_group → framework for easy
     * consumption by the dashboard UI.
     */
    public function index(): JsonResponse
    {
        $rows = CefrActflThreshold::orderBy('skill_group')
            ->orderBy('framework')
            ->orderBy('sort_order')
            ->get();

        // Group: { core: { cefr: [...], actfl: [...] }, productive: { ... } }
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row->skill_group][$row->framework][] = $row;
        }

        return response()->json($grouped);
    }

    /**
     * GET /admin/cefr-actfl-thresholds/flat
     *
     * Flat list — useful for table-style admin views.
     */
    public function flat(): JsonResponse
    {
        $rows = CefrActflThreshold::orderBy('skill_group')
            ->orderBy('framework')
            ->orderBy('sort_order')
            ->get();

        return response()->json($rows);
    }

    /**
     * POST /admin/cefr-actfl-thresholds
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_group' => ['required', Rule::in(CefrActflThreshold::GROUPS)],
            'framework'   => ['required', Rule::in(CefrActflThreshold::FRAMEWORKS)],
            'min_score'   => [
                'required', 'integer', 'min:0', 'max:900',
                Rule::unique('cefr_actfl_thresholds')->where(fn ($q) =>
                    $q->where('skill_group', $request->skill_group)
                      ->where('framework', $request->framework)
                ),
            ],
            'level_label' => 'required|string|max:50',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $validated['sort_order'] = $validated['sort_order']
            ?? CefrActflThreshold::where('skill_group', $validated['skill_group'])
                ->where('framework', $validated['framework'])
                ->max('sort_order') + 1;

        $threshold = CefrActflThreshold::create($validated);

        $this->clearCache();

        return response()->json([
            'message'   => 'Threshold created successfully.',
            'threshold' => $threshold,
        ], 201);
    }

    /**
     * GET /admin/cefr-actfl-thresholds/{threshold}
     */
    public function show(CefrActflThreshold $cefrActflThreshold): JsonResponse
    {
        return response()->json($cefrActflThreshold);
    }

    /**
     * PATCH /admin/cefr-actfl-thresholds/{threshold}
     */
    public function update(Request $request, CefrActflThreshold $cefrActflThreshold): JsonResponse
    {
        $validated = $request->validate([
            'skill_group' => ['sometimes', Rule::in(CefrActflThreshold::GROUPS)],
            'framework'   => ['sometimes', Rule::in(CefrActflThreshold::FRAMEWORKS)],
            'min_score'   => [
                'sometimes', 'integer', 'min:0', 'max:900',
                Rule::unique('cefr_actfl_thresholds')
                    ->where(fn ($q) =>
                        $q->where('skill_group', $request->skill_group ?? $cefrActflThreshold->skill_group)
                          ->where('framework',   $request->framework   ?? $cefrActflThreshold->framework)
                    )
                    ->ignore($cefrActflThreshold->id),
            ],
            'level_label' => 'sometimes|string|max:50',
            'sort_order'  => 'sometimes|integer|min:0',
            'is_active'   => 'sometimes|boolean',
        ]);

        $cefrActflThreshold->update($validated);

        $this->clearCache();

        return response()->json([
            'message'   => 'Threshold updated successfully.',
            'threshold' => $cefrActflThreshold->fresh(),
        ]);
    }

    /**
     * DELETE /admin/cefr-actfl-thresholds/{threshold}
     */
    public function destroy(CefrActflThreshold $cefrActflThreshold): JsonResponse
    {
        $cefrActflThreshold->delete();

        $this->clearCache();

        return response()->json(['message' => 'Threshold deleted successfully.']);
    }

    /**
     * PUT /admin/cefr-actfl-thresholds/bulk-update
     *
     * Replace ALL thresholds for a given skill_group + framework at once.
     * Payload: { skill_group, framework, thresholds: [{ min_score, level_label, sort_order?, is_active? }] }
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_group'                   => ['required', Rule::in(CefrActflThreshold::GROUPS)],
            'framework'                     => ['required', Rule::in(CefrActflThreshold::FRAMEWORKS)],
            'thresholds'                    => 'required|array|min:1',
            'thresholds.*.min_score'        => 'required|integer|min:0|max:900',
            'thresholds.*.level_label'      => 'required|string|max:50',
            'thresholds.*.sort_order'       => 'nullable|integer|min:0',
            'thresholds.*.is_active'        => 'nullable|boolean',
        ]);

        $group     = $validated['skill_group'];
        $framework = $validated['framework'];

        // Delete existing rows for this group+framework and recreate
        CefrActflThreshold::where('skill_group', $group)
            ->where('framework', $framework)
            ->delete();

        foreach ($validated['thresholds'] as $index => $row) {
            CefrActflThreshold::create([
                'skill_group' => $group,
                'framework'   => $framework,
                'min_score'   => $row['min_score'],
                'level_label' => $row['level_label'],
                'sort_order'  => $row['sort_order'] ?? $index,
                'is_active'   => $row['is_active'] ?? true,
            ]);
        }

        $this->clearCache();

        return response()->json([
            'message' => "Thresholds for {$group}/{$framework} replaced successfully.",
            'count'   => count($validated['thresholds']),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function clearCache(): void
    {
        Cache::forget('cefr_actfl_thresholds');
    }
}
