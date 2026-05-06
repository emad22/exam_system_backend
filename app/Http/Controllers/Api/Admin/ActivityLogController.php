<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Http\Resources\ActivityLogResource;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ActivityLogController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'action' => 'nullable|string',
            'model_type' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string',
        ]);

        $query = ActivityLog::with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('action', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
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

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:activity_logs,id'
        ]);

        if (count($request->ids) > 1000) {
            return response()->json(['message' => 'Too many records'], 422);
        }

        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        ActivityLog::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Logs deleted successfully']);
    }
}

