<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ProctoringSession;
use App\Models\ExamViolation;
use App\Models\ProctoringReport;
use App\Models\ExamAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProctoringController extends Controller
{
    /**
     * عرض قائمة جلسات المراقبة
     */
    public function index(Request $request)
    {
        $query = ProctoringSession::with([
            'examAttempt.exam',
            'student',
            'proctor',
        ]);

        // الفلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // البحث عن الطالب
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        // الفلترة حسب الفترة الزمنية
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // الفلترة حسب درجة الخطر
        if ($request->filled('min_risk_score')) {
            $query->where('risk_score', '>=', $request->min_risk_score);
        }

        // الفلترة حسب وجود انتهاكات
        if ($request->filled('has_violations')) {
            if ($request->has_violations) {
                $query->where('violations_count', '>', 0);
            } else {
                $query->where('violations_count', '=', 0);
            }
        }

        // الترتيب
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $sessions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $sessions->items(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
                'last_page' => $sessions->lastPage(),
            ]
        ]);
    }

    /**
     * عرض تفاصيل جلسة المراقبة
     */
    public function show($sessionId)
    {
        $session = ProctoringSession::with([
            'examAttempt.exam',
            'examAttempt.skills',
            'student',
            'proctor',
            'violations' => function ($q) {
                $q->orderBy('timestamp', 'desc');
            },
            'report',
        ])->findOrFail($sessionId);

        $faceDetectionLogs = DB::table('face_detection_logs')
            ->where('proctoring_session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $deviceDetectionLogs = DB::table('device_detection_logs')
            ->where('proctoring_session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // حساب إحصائيات
        $violationsByType = ExamViolation::where('proctoring_session_id', $sessionId)
            ->groupBy('violation_type')
            ->selectRaw('violation_type, COUNT(*) as count')
            ->get();

        $violationsBySeverity = ExamViolation::where('proctoring_session_id', $sessionId)
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as count')
            ->get();

        return response()->json([
            'session' => $session,
            'violations' => $session->violations,
            'face_detection_logs' => $faceDetectionLogs,
            'device_detection_logs' => $deviceDetectionLogs,
            'statistics' => [
                'violations_by_type' => $violationsByType,
                'violations_by_severity' => $violationsBySeverity,
                'total_violations' => $session->violations_count,
                'risk_score' => $session->risk_score,
                'duration_seconds' => $session->duration_seconds,
            ]
        ]);
    }

    /**
     * عرض الانتهاكات لجلسة معينة
     */
    public function violations($sessionId, Request $request)
    {
        $query = ExamViolation::where('proctoring_session_id', $sessionId)
            ->with(['student', 'reviewedBy']);

        // الفلترة حسب النوع
        if ($request->filled('violation_type')) {
            $query->where('violation_type', $request->violation_type);
        }

        // الفلترة حسب الخطورة
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        // الفلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $violations = $query->orderBy('timestamp', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $violations->items(),
            'pagination' => [
                'current_page' => $violations->currentPage(),
                'per_page' => $violations->perPage(),
                'total' => $violations->total(),
                'last_page' => $violations->lastPage(),
            ]
        ]);
    }

    /**
     * مراجعة انتهاك
     */
    public function reviewViolation($violationId, Request $request)
    {
        $violation = ExamViolation::findOrFail($violationId);

        $validated = $request->validate([
            'status' => 'required|in:confirmed,dismissed,suspicious',
            'reviewed_by' => 'required|exists:users,id',
            'proctor_notes' => 'nullable|string|max:1000',
            'action_taken' => 'nullable|in:warning,pause_exam,terminate_exam,report_to_instructor'
        ]);

        $violation->update([
            'status' => $validated['status'],
            'reviewed_at' => now(),
            'reviewed_by' => $validated['reviewed_by'],
            'proctor_notes' => $validated['proctor_notes'] ?? null,
            'action_taken' => $validated['action_taken'] ?? null,
            'flagged_by_proctor' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Violation reviewed successfully',
            'violation' => $violation
        ]);
    }

    /**
     * عرض التقرير
     */
    public function report($sessionId)
    {
        $session = ProctoringSession::with([
            'examAttempt.exam',
            'student',
            'report'
        ])->findOrFail($sessionId);

        $report = $session->report ?? ProctoringReport::create([
            'proctoring_session_id' => $sessionId,
            'generated_by' => auth()->id(),
            'overall_verdict' => $this->calculateVerdict($session),
            'risk_assessment' => $this->generateRiskAssessment($session),
            'recommendations' => $this->generateRecommendations($session),
            'details' => [
                'violations_count' => $session->violations_count,
                'risk_score' => $session->risk_score,
                'duration_seconds' => $session->duration_seconds,
                'identity_verified' => $session->identity_verified,
                'identity_verification_score' => $session->face_verification_score,
            ]
        ]);

        return response()->json([
            'session' => $session,
            'report' => $report
        ]);
    }

    /**
     * تصدير تقرير PDF
     */
    public function exportReport($sessionId)
    {
        $session = ProctoringSession::with([
            'examAttempt.exam',
            'student',
            'violations'
        ])->findOrFail($sessionId);

        // هنا يمكن إضافة logic لتصدير PDF
        return response()->json([
            'message' => 'Report export feature coming soon'
        ]);
    }

    /**
     * تحديث حالة الجلسة
     */
    public function updateStatus($sessionId, Request $request)
    {
        $session = ProctoringSession::findOrFail($sessionId);

        $validated = $request->validate([
            'status' => 'required|in:active,paused,ended,cancelled',
            'final_verdict' => 'nullable|in:pass,fail,review_required'
        ]);

        $session->update([
            'status' => $validated['status'],
            'final_verdict' => $validated['final_verdict'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session status updated',
            'session' => $session
        ]);
    }

    /**
     * الحصول على إحصائيات عامة
     */
    public function statistics(Request $request)
    {
        $fromDate = $request->get('from_date', now()->subDays(30));
        $toDate = $request->get('to_date', now());

        $totalSessions = ProctoringSession::whereBetween('created_at', [$fromDate, $toDate])->count();
        $totalViolations = ExamViolation::whereBetween('created_at', [$fromDate, $toDate])->count();
        $averageRiskScore = ProctoringSession::whereBetween('created_at', [$fromDate, $toDate])->avg('risk_score');
        $sessionsWithViolations = ProctoringSession::whereBetween('created_at', [$fromDate, $toDate])
            ->where('violations_count', '>', 0)->count();

        $violationTypes = ExamViolation::whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('violation_type')
            ->selectRaw('violation_type, COUNT(*) as count')
            ->get();

        $violationsBySeverity = ExamViolation::whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as count')
            ->get();

        return response()->json([
            'total_sessions' => $totalSessions,
            'total_violations' => $totalViolations,
            'average_risk_score' => round($averageRiskScore, 2),
            'sessions_with_violations' => $sessionsWithViolations,
            'violation_types' => $violationTypes,
            'violations_by_severity' => $violationsBySeverity,
        ]);
    }

    /**
     * حساب الحكم النهائي
     */
    private function calculateVerdict($session)
    {
        if (!$session->identity_verified) {
            return 'fail';
        }

        if ($session->risk_score > 80) {
            return 'fail';
        }

        if ($session->risk_score > 50) {
            return 'review_required';
        }

        return 'pass';
    }

    /**
     * إنشاء تقييم الخطر
     */
    private function generateRiskAssessment($session)
    {
        $criticalViolations = $session->violations()
            ->where('severity', 'critical')
            ->count();

        $highViolations = $session->violations()
            ->where('severity', 'high')
            ->count();

        $assessment = [];
        if ($criticalViolations > 0) {
            $assessment[] = "لقد تم اكتشاف {$criticalViolations} انتهاكات حرجة";
        }
        if ($highViolations > 0) {
            $assessment[] = "لقد تم اكتشاف {$highViolations} انتهاكات عالية الخطورة";
        }

        if (!$session->identity_verified) {
            $assessment[] = "لم يتم التحقق من الهوية بنجاح";
        }

        return $assessment;
    }

    /**
     * إنشاء التوصيات
     */
    private function generateRecommendations($session)
    {
        $recommendations = [];

        if ($session->risk_score > 70) {
            $recommendations[] = "يُوصى بفحص الجلسة الكامل";
            $recommendations[] = "يُوصى بإعادة الاختبار تحت مراقبة أكثر صرامة";
        }

        if ($session->violations()->where('violation_type', 'multiple_faces')->exists()) {
            $recommendations[] = "يُوصى بالتحقق من هوية الشخص";
        }

        if ($session->violations()->where('violation_type', 'tab_switched')->count() > 5) {
            $recommendations[] = "يُوصى بإجراء مراقبة تفصيلية للنشاط";
        }

        return $recommendations;
    }
}
