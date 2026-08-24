<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Passage\StorePassageRequest;
use App\Http\Requests\Admin\Passage\UpdatePassageRequest;
use App\Models\Passage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PassageController extends Controller
{
    /**
     * Display a listing of the passages.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Passage::class);
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
    public function store(StorePassageRequest $request)
    {
        $this->authorize('create', Passage::class);
        $validated = $request->validated();

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
        $this->authorize('view', $passage);
        return response()->json($passage->load('questions'));
    }

    /**
     * Update the specified passage in storage.
     */
    public function update(UpdatePassageRequest $request, Passage $passage)
    {
        $this->authorize('update', $passage);
        $validated = $request->validated();

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
        $this->authorize('delete', $passage);
        $passage->delete();
        return response()->json(['message' => 'Passage deleted successfully']);
    }
}
