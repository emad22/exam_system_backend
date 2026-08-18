<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Proctoring\ProctoringController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ProctoringSession;
use App\Models\Student;
use App\Models\User;
use App\Services\ProctoringSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'student',
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $validated['exam_category_id'],
            'registration_source' => 'website',
            'registration_date' => now(),
        ]);

        // Automated Exam Enrollment & Skill Filtering
        Student::assignDefaultExam($student);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully',
            'token' => $token,
            'user' => $user->load('student')
        ], 201);
    }

    public function login(Request $request)
    {
        $login = $request->input('login') ?? $request->input('email');

        $user = User::where(function ($query) use ($login) {
            $query->where('email', $login)
                ->orWhere('username', $login);
        })->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }


        // ✅ تحقق من الحالة
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Your account is deactivated. Please contact admin.'
            ], 403);
        }

        $isDemo = app(\App\Services\ExamService::class)->isDemoUser($user);

        // Close any active proctoring sessions for the student since login session is replaced
        if ($user->student && !$isDemo) {
            $activeSessions = ProctoringSession::where('student_id', $user->student->id)
                ->whereIn('status', ['pending', 'active', 'paused'])
                ->get();

            foreach ($activeSessions as $session) {
                $duration = 0;
                if ($session->started_at) {
                    $totalSeconds = abs(now()->diffInSeconds($session->started_at));
                    $storedPausedSeconds = (int) ($session->total_paused_seconds ?? 0);
                    $currentPausePeriod = 0;
                    if ($session->status === 'paused' && $session->paused_at) {
                        $currentPausePeriod = abs(now()->diffInSeconds($session->paused_at));
                    }
                    $totalPaused = $storedPausedSeconds + $currentPausePeriod;
                    $duration = (int) max(0, $totalSeconds - $totalPaused);
                }

                $session->update([
                    'status' => 'ended',
                    'recording_status' => 'completed',
                    'ended_at' => now(),
                    'closed_at' => now(),
                    'close_reason' => 'session_replaced',
                    'duration_seconds' => $duration,
                ]);
            }

            // Create a new pending proctoring session for this login
            ProctoringSession::create([
                'student_id' => $user->student->id,
                'exam_attempt_id' => null,
                'status' => 'pending',
                'session_token' => \Illuminate\Support\Str::random(64),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'risk_score' => 0,
                'violations_count' => 0,
            ]);
        }

        if (!$isDemo) {
            $user->tokens()->delete();
        }

        $deviceName = $request->input('device_name', 'auth_token');
        $newToken = $user->createToken($deviceName);

        $user->update(['last_token_id' => $newToken->accessToken->id]);

        $token = $newToken->plainTextToken;


        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'user' => $user->load('student')
        ]);
    }

    /**
     * Get the authenticated user profile with necessary relationships.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if ($user->student) {
            $user->load([
                'student' => function ($query) {
                    $query->select('id', 'user_id', 'partner_id', 'exam_category_id', 'student_code', 'is_demo', 'is_demo_proctored', 'bypass_identity_verification');
                },
                'student.partner' => function ($query) {
                    $query->select('id', 'partner_name', 'proctoring_required', 'proctoring_mode');
                },
                'student.category' => function ($query) {
                    $query->select('id', 'name');
                }
            ]);
        }

        return response()->json($user);
    }

    // public function logout(Request $request)
    // {
    //     $user = $request->user();

    //     $studentId = $user->student?->id;

    //     if ($studentId) {
    //         $session = ProctoringSession::where('student_id', $studentId)
    //             ->where('status', 'active')

    //             ->latest()
    //             ->first();

    //         if ($session) {
    //             app(ProctoringController::class)
    //                 ->closeSession($session->id, $request);
    //         }
    //     }
    //     // Log::info('User logged out', [
    //     //     'user_id' => $user->id,
    //     //     'student_id' => $user->student?->id,
    //     //     'email' => $user->email,
    //     //     'ip' => $request->ip(),
    //     //     'user_agent' => $request->userAgent(),
    //     //     'time' => now(),
    //     // ]);

    //     $user->currentAccessToken()?->delete();

    //     return response()->json([
    //         'message' => 'Logged out successfully'
    //     ]);
    // }



    public function logout(Request $request)
    {
        $user = $request->user();
        $studentId = $user->student?->id;

        if ($studentId) {
            $sessions = ProctoringSession::where('student_id', $studentId)
                ->whereIn('status', ['pending', 'active', 'paused'])
                ->get();

            foreach ($sessions as $session) {
                // ✅ نقفل أي جلسة مراقبة مفتوحة قبل تسجيل الخروج
                app(ProctoringSessionService::class)->end($session, 'student_left');
            }
        }

        $token = $user->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }
}