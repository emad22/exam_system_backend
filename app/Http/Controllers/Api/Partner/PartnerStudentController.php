<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerStudentController extends Controller
{
    /**
     * Get students belonging to the authenticated partner
     */
    public function index(Request $request)
    {
        $partnerId = $request->user()->partner->id ?? null;

        if (!$partnerId) {
            return response()->json(['message' => 'Partner profile not found.'], 404);
        }

        $query = Student::where('partner_id', $partnerId)
            ->with([
                'user:id,first_name,last_name,username,email,phone,avatar,is_active',
                'package:id,name',
                'category:id,name',
                'attempts' => function ($q) {
                    $q->select('id', 'student_id', 'exam_id', 'status', 'overall_score', 'created_at')
                      ->latest()
                      ->take(5);
                }
            ])
            ->withCount([
                'attempts',
                'attempts as completed_attempts_count' => function ($q) {
                    $q->where('status', 'completed');
                }
            ])
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                  ->orWhere('student_code', 'like', "%{$search}%")
                  ->orWhere('institution_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('id', $search)
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('from_date')) {
            $fromDate = $request->from_date;
            $query->where(function ($q) use ($fromDate) {
                $q->whereDate('registration_date', '>=', $fromDate)
                  ->orWhere(function ($sub) use ($fromDate) {
                      $sub->whereNull('registration_date')
                          ->whereDate('created_at', '>=', $fromDate);
                  });
            });
        }

        if ($request->filled('to_date')) {
            $toDate = $request->to_date;
            $query->where(function ($q) use ($toDate) {
                $q->whereDate('registration_date', '<=', $toDate)
                  ->orWhere(function ($sub) use ($toDate) {
                      $sub->whereNull('registration_date')
                          ->whereDate('created_at', '<=', $toDate);
                  });
            });
        }

        $perPage = (int) $request->input('per_page', 50);
        $students = $query->paginate($perPage);

        return response()->json($students);
    }
}
