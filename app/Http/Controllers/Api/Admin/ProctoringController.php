<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ExamAttemptLevel;
use App\Models\ProctoringSession;
use App\Models\ExamViolation;
use App\Models\ProctoringReport;
use App\Models\ExamAttempt;
use App\Http\Controllers\Controller;
use App\Services\AttemptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ExamExitedNotification;
use App\Models\User;

class ProctoringController extends Controller
{
    public function __construct(
        private readonly AttemptService $attemptService
    ) {
    }
    /**
     * عرض قائمة جلسات المراقبة
     */
    public function index(Request $request)
    {
        if ($request->get('group_by') === 'student') {
            $query = \App\Models\Student::whereHas('proctoringSessions')
                ->with('user');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%");
                });
            }

            $perPage = $request->get('per_page', 15);
            $students = $query->paginate($perPage);

            $data = collect($students->items())->map(function ($student) {
                $sessions = ProctoringSession::where('student_id', $student->id)->get();
                $latestSession = ProctoringSession::where('student_id', $student->id)
                    ->with('examAttempt.exam')
                    ->latest()
                    ->first();

                $totalViolations = $sessions->sum('violations_count');
                $maxRiskScore = $sessions->max('risk_score') ?? 0;

                return [
                    'student_id' => $student->id,
                    'student' => [
                        'id' => $student->id,
                        'user' => [
                            'name' => $student->user?->name,
                            'email' => $student->user?->email,
                        ]
                    ],
                    'sessions_count' => $sessions->count(),
                    'violations_count' => $totalViolations,
                    'risk_score' => $maxRiskScore,
                    'created_at' => $latestSession?->created_at ? $latestSession->created_at->toDateTimeString() : null,
                    'latest_session_id' => $latestSession?->id,
                    'exam_title' => $latestSession?->examAttempt?->exam?->title,
                ];
            });

            return response()->json([
                'data' => $data,
                'pagination' => [
                    'current_page' => $students->currentPage(),
                    'per_page' => $students->perPage(),
                    'total' => $students->total(),
                    'last_page' => $students->lastPage(),
                ]
            ]);
        }

        $query = ProctoringSession::with([
            'examAttempt.exam',
            'student.user',
            'skills',
            // 'proctor',
        ]);

        // الفلترة حسب الحالة
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // البحث عن الطالب
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student.user', function ($q) use ($search) {
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
     * عرض جميع جلسات الطالب المحددة بالمراقبة
     */
    public function studentSessions($studentId)
    {
        $student = \App\Models\Student::with('user')->findOrFail($studentId);

        $sessions = ProctoringSession::where('student_id', $studentId)
            ->with([
                'examAttempt.exam',
                'skills'
            ])
            ->latest()
            ->get();

        return response()->json([
            'student' => $student,
            'sessions' => $sessions
        ]);
    }

    /**
     * عرض تفاصيل جلسة المراقبة
     */
    public function show($sessionId)
    {
        $session = ProctoringSession::with([
            'examAttempt.exam',
            'student.user',  // ← أضف .user
            'proctor',
            'violations' => function ($q) {
                $q->orderBy('timestamp', 'desc');
            },
            'report',
        ])->findOrFail($sessionId);

        $faceDetectionLogs = DB::table('face_detection_logs')
            ->where('proctoring_session_id', $sessionId)
            ->orderBy('timestamp', 'desc')
            ->limit(50)
            ->get();

        $deviceDetectionLogs = DB::table('device_detection_logs')
            ->where('proctoring_session_id', $sessionId)
            ->orderBy('detected_at', 'desc')
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
            'proctor_notes' => 'nullable|string|max:1000',
            'action_taken' => 'nullable|in:warning,pause_exam,terminate_exam,report_to_instructor'
        ]);

        $violation->update([
            'status' => $validated['status'],
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),   // always use authenticated user
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
        $session = ProctoringSession::with('examAttempt')->findOrFail($sessionId);

        $validated = $request->validate([
            'status' => 'required|in:active,paused,ended,cancelled',
            'final_verdict' => 'nullable|in:pass,fail,review_required'
        ]);

        $newStatus = $validated['status'];

        $fields = [
            'status' => $newStatus,
            'final_verdict' => $validated['final_verdict'] ?? null,
        ];

        match ($newStatus) {
            'active' => $fields += [
                'recording_status' => 'recording',
                'resumed_at' => now(),
                'paused_at' => null,
            ],
            'paused' => $fields += [
                'recording_status' => 'paused',
                'paused_at' => now(),
            ],
            'ended', 'cancelled' => $fields += [
                'recording_status' => 'completed',
                'ended_at' => now(),
                'duration_seconds' => $session->started_at
                    ? now()->diffInSeconds($session->started_at)
                    : null,
            ],
            default => null,
        };

        $session->update($fields);

        // إذا تم إنهاء الجلسة، قم بإنهاء الامتحان أيضاً
        if (in_array($newStatus, ['ended', 'cancelled']) && $session->examAttempt) {
            $this->closeExamAttempt($session->examAttempt);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session status updated',
            'session' => $session->fresh()
        ]);
    }

    /**
     * إنهاء محاولة الامتحان
     */
    private function closeExamAttempt(ExamAttempt $attempt): void
    {
        if ($attempt->status === 'completed') {
            return;
        }

        // إرسال إشعارات للمشرفين
        $admins = User::whereIn('role', ['admin', 'teacher'])->get();
        Notification::send($admins, new ExamExitedNotification($attempt));

        // Finalize active skill + log active level + update completed_skills
        $this->attemptService->finalizeActiveSkillAndLevelOnExit($attempt);
        $attempt->refresh();

        // إنهاء المحاولة بالكامل
        $this->attemptService->completeAttempt($attempt);
    }

    /**
     * إنهاء مهارة الطالب الحالية وإخراجه من الامتحان
     * يُستدعى عندما يضغط الأدمن على "End Skill Exam"
     */
    public function endSkillExam($sessionId, Request $request)
    {
        $session = ProctoringSession::with('examAttempt')->findOrFail($sessionId);

        $attempt = $session->examAttempt;

        if (!$attempt) {
            return response()->json([
                'success' => false,
                'message' => 'No exam attempt linked to this session.'
            ], 422);
        }

        // ==============================
        // Get current active skill
        // ==============================
        $pos = $attempt->current_position ?? [];

        $currentIndex = $pos['current_skill_index'] ?? null;
        $skillIds = $pos['skill_ids'] ?? [];

        if ($currentIndex === null || !isset($skillIds[$currentIndex])) {
            return response()->json([
                'success' => false,
                'message' => 'The student is not currently taking any active skill exam.'
            ], 422);
        }

        $skillId = $skillIds[$currentIndex];

        // ==============================
        // Compute Skill Score
        // ==============================
        $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);

        $maxLevel = ExamAttemptLevel::where('exam_attempt_id', $attempt->id)
            ->where('skill_id', $skillId)
            ->max('level_number') ?? 1;

        // ==============================
        // Finalize current skill
        // ==============================
        $this->attemptService->finalizeSkill(
            $attempt,
            $skillId,
            $skillScore,
            $maxLevel,
            'completed'
        );

        // ==============================
        // Update overall score
        // ==============================
        $this->attemptService->updateOverallScore(
            $attempt,
            $skillId,
            $skillScore
        );

        // ==============================
        // Move to next skill
        // ==============================
        $result = $this->attemptService->advanceToNextSkillOrFinish(
            $attempt,
            $pos,
            $skillId
        );

        $attempt->update([
            'current_position' => $result['next_pos']
        ]);

        // ==============================
        // Finish exam if last skill
        // ==============================
        if ($result['finished_exam']) {
            $this->attemptService->completeAttempt($attempt);

            if ($session->status !== 'ended') {
                $session->update([
                    'status' => 'ended',
                    'ended_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'finished_exam' => true,
                'message' => 'The final skill has been ended. Exam completed successfully.',
                'skill_id' => $skillId,
                'session' => $session->fresh(),
                'attempt' => $attempt->fresh(),
            ]);
        }

        // ==============================
        // Get next skill
        // ==============================
        $attempt->refresh();

        $nextPos = $attempt->current_position;

        $nextSkillId = null;

        if (
            isset($nextPos['current_skill_index']) &&
            isset($nextPos['skill_ids'][$nextPos['current_skill_index']])
        ) {
            $nextSkillId = $nextPos['skill_ids'][$nextPos['current_skill_index']];
        }

        return response()->json([
            'success' => true,
            'finished_exam' => false,
            'message' => 'Skill exam ended successfully.',
            'completed_skill_id' => $skillId,
            'next_skill_id' => $nextSkillId,
            'session' => $session->fresh(),
            'attempt' => $attempt,
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

    /**
     * Delete a proctoring session
     */
    public function destroy($sessionId)
    {
        $session = ProctoringSession::findOrFail($sessionId);

        // حذف البيانات المرتبطة بالجلسة أولاً
        ExamViolation::where('proctoring_session_id', $sessionId)->delete();
        DB::table('face_detection_logs')->where('proctoring_session_id', $sessionId)->delete();
        DB::table('device_detection_logs')->where('proctoring_session_id', $sessionId)->delete();

        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Proctoring session deleted successfully.'
        ]);
    }

    /**
     * Delete ALL proctoring sessions for a specific student
     */
    public function deleteAllStudentSessions($studentId)
    {
        $sessionIds = ProctoringSession::where('student_id', $studentId)->pluck('id');

        if ($sessionIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No sessions found for this student.',
                'deleted_count' => 0,
            ]);
        }

        // حذف كل البيانات المرتبطة بجلسات الطالب
        ExamViolation::whereIn('proctoring_session_id', $sessionIds)->delete();
        DB::table('face_detection_logs')->whereIn('proctoring_session_id', $sessionIds)->delete();
        DB::table('device_detection_logs')->whereIn('proctoring_session_id', $sessionIds)->delete();
        DB::table('proctoring_reports')->whereIn('proctoring_session_id', $sessionIds)->delete();

        $count = ProctoringSession::where('student_id', $studentId)->delete();

        return response()->json([
            'success' => true,
            'message' => "All {$count} proctoring sessions for this student have been deleted.",
            'deleted_count' => $count,
        ]);
    }

    /**
     * Bulk delete proctoring sessions
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:proctoring_sessions,id',
        ]);

        // حذف البيانات المرتبطة بالجلسات أولاً
        ExamViolation::whereIn('proctoring_session_id', $validated['ids'])->delete();
        DB::table('face_detection_logs')->whereIn('proctoring_session_id', $validated['ids'])->delete();
        DB::table('device_detection_logs')->whereIn('proctoring_session_id', $validated['ids'])->delete();

        ProctoringSession::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success' => true,
            'message' => 'Selected proctoring sessions deleted successfully.'
        ]);
    }
}
