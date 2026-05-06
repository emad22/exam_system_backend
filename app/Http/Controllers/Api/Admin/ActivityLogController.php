<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->latest();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('model_type')) {
            $query->where('model_type', 'LIKE', '%' . $request->model_type . '%');
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return response()->json($query->paginate(50));
    }

    public function show($id)
    {
        $log = ActivityLog::with('user')->findOrFail($id);
        return response()->json($log);
    }

    public function destroy($id)
    {
        $log = ActivityLog::findOrFail($id);
        $log->delete();
        return response()->json(['message' => 'Log deleted successfully']);
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:activity_logs,id'
        ]);

        ActivityLog::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'Logs deleted successfully']);
    }
}
