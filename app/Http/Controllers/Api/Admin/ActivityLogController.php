<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Http\Resources\ActivityLogResource;
use App\Http\Requests\Admin\ActivityLog\IndexRequest;
use App\Http\Requests\Admin\ActivityLog\BulkDestroyRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ActivityLogController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexRequest $request)
    {
        $validated = $request->validated();

        $query = ActivityLog::with('user')->latest();

        if ($validated['user_id'] ?? null) {
            $query->where('user_id', $validated['user_id']);
        }

        if ($validated['action'] ?? null) {
            $query->where('action', $validated['action']);
        }

        if ($validated['model_type'] ?? null) {
            $query->where('model_type', $validated['model_type']);
        }

        if ($validated['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if ($validated['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if ($validated['search'] ?? null) {
            $query->where(function ($q) use ($validated) {
                $q->where('action', 'like', "%{$validated['search']}%")
                  ->orWhere('description', 'like', "%{$validated['search']}%");
            });
        }

        return ActivityLogResource::collection($query->paginate(50));
    }

    public function show($id)
    {
        $log = ActivityLog::with('user')->findOrFail($id);
        return new ActivityLogResource($log);
    }

    public function destroy($id)
    {
        $log = ActivityLog::findOrFail($id);
        
        // $this->authorize('delete', $log); // Assuming a policy exists or adding a custom check
        if (auth()->user()->role !== 'admin') {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        $log->delete();
        return response()->json(['message' => 'Log deleted successfully']);
    }

    public function bulkDestroy(BulkDestroyRequest $request)
    {
        $validated = $request->validated();

        if (count($validated['ids']) > 1000) {
            return response()->json(['message' => 'Too many records'], 422);
        }

        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        ActivityLog::whereIn('id', $validated['ids'])->delete();
        return response()->json(['message' => 'Logs deleted successfully']);
    }
}

