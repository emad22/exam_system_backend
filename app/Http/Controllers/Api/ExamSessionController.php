<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSkill;
use App\Models\Skill;
use App\Models\StudentExamConfig;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\ExamService;
use App\Services\QuestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Notification;
use Illuminate\Support\Facades\Auth;

class ExamSessionController extends Controller
{
    public function __construct(
        private readonly ExamService $examService,
        private readonly QuestionService $questionService,
        private readonly AttemptService $attemptService,
    ) {}

    /**
     * List all available exams for the student.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return response()->json(Exam::with('category', 'skills')->get());
        }

        if ($this->examService->isDemoUser($user)) {
            return response()->json($this->buildDemoExamList($user));
        }

        $studentProfile = $user->student;
        if (!$studentProfile) {
            return response()->json([]);
        }

        $assignedExamIds = $studentProfile->configs()->pluck('exam_id')->toArray();

        if ($studentProfile->package && $studentProfile->package->exam_id) {
            if (!in_array($studentProfile->package->exam_id, $assignedExamIds)) {
                $assignedExamIds[] = $studentProfile->package->exam_id;
            }
        }

        $exams = Exam::whereIn('id', $assignedExamIds)->with(['language', 'skills'])->get();

        $allowedSkillIdentifiers = $this->examService->getAllowedSkills($studentProfile);

        $latestAttempts = ExamAttempt::where('student_id', $studentProfile->id)
            ->whereIn('exam_id', $assignedExamIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('exam_id')
            ->map(fn($g) => $g->first());

        $skillStatusRecords = DB::table('exam_attempt_skills')
            ->join('exam_attempts', 'exam_attempt_skills.exam_attempt_id', '=', 'exam_attempts.id')
            ->where('exam_attempts.student_id', $studentProfile->id)
            ->whereIn('exam_attempts.exam_id', $assignedExamIds)
            ->select('exam_attempts.exam_id', 'exam_attempt_skills.skill_id', 'exam_attempt_skills.status')
            ->get();

        $skillStatusMap = [];
        foreach ($skillStatusRecords as $record) {
            $currentStatus = $skillStatusMap[$record->exam_id][$record->skill_id] ?? null;
            if (in_array($record->status, ['completed', 'failed'])) {
                $skillStatusMap[$record->exam_id][$record->skill_id] = $record->status;
            } elseif (!$currentStatus || $currentStatus === 'skipped') {
                $skillStatusMap[$record->exam_id][$record->skill_id] = $record->status;
            }
        }

        $getOrder = function($name) {
            $name = strtolower($name);
            if (str_contains($name, 'listening')) return 1;
            if (str_contains($name, 'reading')) return 2;
            if (str_contains($name, 'structure') || str_contains($name, 'grammar') || str_contains($name, 'gram')) return 3;
            if (str_contains($name, 'writing') || str_contains($name, 'writting') || str_contains($name, 'writ')) return 4;
            if (str_contains($name, 'speaking') || str_contains($name, 'speak')) return 5;
            return 99;
        };

        $exams->each(function ($exam) use ($allowedSkillIdentifiers, $latestAttempts, $skillStatusMap, $getOrder) {
            if (!empty($allowedSkillIdentifiers)) {
                $exam->setRelation('skills', $this->examService->filterSkills($exam->skills, $allowedSkillIdentifiers));
            }
            if ($exam->skills) {
                $sortedSkills = $exam->skills->sortBy(fn($s) => $getOrder($s->name))->values();
                $exam->setRelation('skills', $sortedSkills);
            }
            $exam->latest_attempt = $latestAttempts->get($exam->id);
            $exam->skill_statuses = $skillStatusMap[$exam->id] ?? [];
            $exam->completed_skill_ids = collect($exam->skill_statuses)
                ->filter(fn($status) => in_array($status, ['completed', 'failed', 'skipped']))
                ->keys()->values();
        });

        return response()->json($exams);
    }

    public function show(Exam $exam)
    {
        $exam->load(['skills', 'category']);
        if ($exam->skills) {
            $getOrder = function($name) {
                $name = strtolower($name);
                if (str_contains($name, 'listening')) return 1;
                if (str_contains($name, 'reading')) return 2;
                if (str_contains($name, 'structure') || str_contains($name, 'grammar') || str_contains($name, 'gram')) return 3;
                if (str_contains($name, 'writing') || str_contains($name, 'writting') || str_contains($name, 'writ')) return 4;
                if (str_contains($name, 'speaking') || str_contains($name, 'speak')) return 5;
                return 99;
            };
            $sortedSkills = $exam->skills->sortBy(fn($s) => $getOrder($s->name))->values();
            $exam->setRelation('skills', $sortedSkills);
        }
        return response()->json($exam);
    }

    public function showAttempt(ExamAttempt $attempt)
    {
        $this->authorize('view', $attempt);
        return response()->json($attempt->load(['exam', 'student']));
    }

    /**
     * Start or Resume an exam.
     */
    public function start(Request $request, Exam $exam)
    {
        $user = Auth::user();
        $isDemo = $this->examService->isDemoUser($user);
        $studentProfile = $user->student;

        if (!$studentProfile && !$isDemo) {
            return response()->json(['error' => 'Student profile not found.'], 404);
        }

        $ownerKey = $isDemo ? 'user_id' : 'student_id';
        $ownerId  = $isDemo ? $user->id : $studentProfile->id;

        $requestedSkillId = $request->has('skill_id')
            ? (int) $request->skill_id
            : null;

        // latest attempt
        $attempt = ExamAttempt::where($ownerKey, $ownerId)
                                ->where('exam_id', $exam->id)
                                ->orderByDesc('created_at')
                                ->first();

        /*
        |----------------------------------------------------
        | 1. Create attempt if not exists
        |----------------------------------------------------
        */
        if (!$attempt) {
            $attempt = $this->createNewAttempt( $request, $exam, $user, $studentProfile,  $isDemo );
            if (!$attempt) {
                return response()->json(['error' => 'Exam not assigned.'], 403);
            }

            if ($attempt === 'no_skills') {
                return response()->json(['error' => 'No skills activated.'], 422);
            }
        }

        /*
        |----------------------------------------------------
        | 2. Validate skill re-entry
        |----------------------------------------------------
        */
        if ($requestedSkillId && !$isDemo) {

            $hasCompletedSkill = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $requestedSkillId)
                ->whereIn('status', ['completed', 'failed', 'skipped'])
                ->exists();

            if ($hasCompletedSkill) {
                return response()->json([
                    'error' => 'You have already completed this module.'
                ], 403);
            }
        }

        /*
        |----------------------------------------------------
        | 3. Re-sync skills (ONLY if needed)
        |----------------------------------------------------
        */
        if (!$isDemo && $studentProfile) {

            $allowedSkillIdentifiers = $this->examService->getAllowedSkills($studentProfile);

            $assignedSkills = collect($exam->skills)
                ->filter(fn($skill) =>
                    $this->examService->skillMatchesIdentifiers($skill, $allowedSkillIdentifiers)
                )
                ->values();

            $order = function ($name) {
                $name = strtolower($name);

                return match (true) {
                    str_contains($name, 'listening') => 1,
                    str_contains($name, 'reading')    => 2,
                    str_contains($name, 'structure'),
                    str_contains($name, 'grammar')    => 3,
                    str_contains($name, 'writing')    => 4,
                    str_contains($name, 'speaking')   => 5,
                    default => 99,
                };
            };

            $assignedSkills = $assignedSkills->sortBy(fn($s) => $order($s->name));

            $assignedSkillIds = $assignedSkills->pluck('id')->values()->toArray();

            $pos = $attempt->current_position ?? [];

            if (($pos['skill_ids'] ?? []) !== $assignedSkillIds) {
                $pos['skill_ids'] = $assignedSkillIds;

                $attempt->update([
                    'current_position' => $pos
                ]);
            }
        }

        /*
        |----------------------------------------------------
        | 4. Resume attempt
        |----------------------------------------------------
        */
        if ($attempt->status !== 'ongoing') {
            $attempt->update(['status' => 'ongoing']);
        }

        $attempt = $this->handleResumeAttempt( $request, $attempt, $exam,  $user, $isDemo);

        /*
        |----------------------------------------------------
        | 5. Sort skills for response
        |----------------------------------------------------
        */
        $skillIds = $attempt->current_position['skill_ids'] ?? [];

        $sortedAssignedSkills = Skill::whereIn('id', $skillIds)
            ->get()
            ->sortBy(fn($s) => array_search($s->id, $skillIds))
            ->values();

        return response()->json([
            'attempt' => $attempt->load('exam'),
            'assigned_skills' => $sortedAssignedSkills,
        ]);
    }

    /**
     * Explicitly finish an exam attempt.
     */
    public function finish(ExamAttempt $attempt)
    {
        $this->authorize('update', $attempt);
        if ($attempt->status === 'ongoing') {
            $admins = User::whereIn('role', ['admin', 'teacher'])->get();
            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\ExamExitedNotification($attempt));

            $pos = $attempt->current_position ?? [];
            if (!empty($pos['skill_ids']) && isset($pos['current_skill_index'])) {
                $skillId = $pos['skill_ids'][$pos['current_skill_index']];
                $skillScore = $this->attemptService->computeSkillScore($attempt, $skillId);
                $maxLevel = \App\Models\ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->where('skill_id', $skillId)->max('level_number') ?? 1;
                $this->attemptService->finalizeSkill($attempt, $skillId, $skillScore, $maxLevel, 'completed');
                $this->attemptService->updateOverallScore($attempt, $skillId, $skillScore);
            }
            $this->attemptService->completeAttempt($attempt);
        }
        return response()->json(['success' => true]);
    }

    public function resetDemo(Request $request, Exam $exam)
    {
        $user = $request->user();
        if (!$this->examService->isDemoUser($user)) return response()->json(['error' => 'Unauthorized.'], 403);
        ExamAttempt::where('user_id', $user->id)->where('exam_id', $exam->id)->delete();
        return response()->json(['message' => 'Demo progress reset.']);
    }

    // --- Private Helpers ---

    private function buildDemoExamList($user): array
    {
        $exams = Exam::with(['category', 'skills'])->get();
        $demoAttempts = ExamAttempt::where('user_id', $user->id)->whereIn('exam_id', $exams->pluck('id'))->orderBy('created_at', 'desc')->get()->groupBy('exam_id')->map(fn($g) => $g->first());
        $exams->each(function ($exam) use ($demoAttempts) {
            $exam->latest_attempt = $demoAttempts->get($exam->id);
            $exam->completed_skill_ids = [];
        });
        return $exams->toArray();
    }

    private function handleResumeAttempt(Request $request, ExamAttempt $attempt, Exam $exam, $user, bool $isDemo): ExamAttempt
    {
        if (!$request->has('skill_id')) return $attempt;
        $requestedSkillId = (int) $request->skill_id;
        $pos = $attempt->current_position;
        $skillIndex = array_search($requestedSkillId, $pos['skill_ids']);
        if ($skillIndex === false) return $attempt;

        if ($isDemo) {
            $isFinished = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)->where('skill_id', $requestedSkillId)->whereIn('status', ['completed', 'failed'])->exists();
            $requestedLevel = $request->has('level_id') ? $this->questionService->getValidStartingLevel($exam->id, $requestedSkillId, (int) $request->level_id) : 1;
            if ($isFinished) {
                $this->attemptService->completeAttempt($attempt);
                $newAttempt = ExamAttempt::create(
                    [
                        'user_id' => Auth::id(), 
                        'exam_id' => $exam->id, 
                        'status' => 'ongoing', 
                        'started_at' => now(), 
                        'current_position' => [
                            'skill_ids' => $pos['skill_ids'], 
                            'current_skill_index' => $skillIndex, 
                            'current_level' => $requestedLevel, 
                            'current_skill_started_at' => null
                            ]
                    ]
                );
                
                ExamAttemptSkill::create([
                    'exam_attempt_id' => $newAttempt->id,
                    'skill_id' => $requestedSkillId,
                    'started_at' => $newAttempt->started_at,
                    'status' => 'in_progress'
                ]);

                return $newAttempt;
            }
            \App\Models\StudentAnswer::where('exam_attempt_id', $attempt->id)->whereHas('question', fn($q) => $q->where('skill_id', $requestedSkillId))->delete();
            \App\Models\ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->where('skill_id', $requestedSkillId)->delete();
            $pos['current_level'] = $requestedLevel;
        }

       
        if ($pos['current_skill_index'] !== $skillIndex) {
            $existingSkill = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
                ->where('skill_id', $requestedSkillId)->first();
            
            $pos['current_skill_started_at'] = ($existingSkill && $existingSkill->started_at) 
                ? $existingSkill->started_at->toIso8601String() 
                : null;
                
        }


        $pos['current_skill_index'] = $skillIndex;
        $attempt->update(['current_position' => $pos]);
        return $attempt;
    }

    private function createNewAttempt(Request $request, Exam $exam, $user, $studentProfile, bool $isDemo): ExamAttempt|string|null
    {
        if (!$isDemo) {
            $config = StudentExamConfig::where('student_id', $studentProfile->id ?? 0)->where('exam_id', $exam->id)->first();
            if (!$config) {
                $config = \App\Models\Student::assignDefaultExam($studentProfile, $exam->id); //368
                if (!$config) return null;
            }
        }
        $allowedSkillIdentifiers = $this->examService->getAllowedSkills($studentProfile);
        if (empty($allowedSkillIdentifiers)) $allowedSkillIdentifiers = $exam->skills->pluck('name')->toArray();
        
        $assignedSkills = [];
        foreach ($exam->skills as $skill) {
            if ($this->examService->skillMatchesIdentifiers($skill, $allowedSkillIdentifiers)) $assignedSkills[] = $skill;
        }
        if (empty($assignedSkills)) return 'no_skills';

        // Sort skills based on the user's custom ordering map
        $getOrder = function($name) {
            $name = strtolower($name);
            if (str_contains($name, 'listening')) return 1;
            if (str_contains($name, 'reading')) return 2;
            if (str_contains($name, 'structure') || str_contains($name, 'grammar') || str_contains($name, 'gram')) return 3;
            if (str_contains($name, 'writing') || str_contains($name, 'writting') || str_contains($name, 'writ')) return 4;
            if (str_contains($name, 'speaking') || str_contains($name, 'speak')) return 5;
            return 99;
        };

        usort($assignedSkills, function($a, $b) use ($getOrder) {
            return $getOrder($a->name) - $getOrder($b->name);
        });

        $assignedSkillIds = array_map(fn($s) => $s->id, $assignedSkills);

        $startIndex = 0;
        if ($request->has('skill_id')) {
            $found = array_search((int) $request->skill_id, $assignedSkillIds);
            if ($found !== false) $startIndex = $found;
        }

        $requestedLevel = ($isDemo && $request->has('level_id')) ? (int) $request->level_id : 1;

        $startingLevel = $this->questionService->getValidStartingLevel(
            $exam->id, 
            $assignedSkillIds[$startIndex] ?? 0, 
            $requestedLevel
        );

        // ✅ لو non-demo، إجبر البداية من 1 بغض النظر عن الـ getValidStartingLevel
        if (!$isDemo) {
            $startingLevel = 1;
        }

        $attempt = ExamAttempt::create(
            [
                'student_id' => $studentProfile?->id, 
                'user_id' => Auth::id(),
                'exam_id' => $exam->id, 
                'status' => 'ongoing',
                'ip_address' => $request->ip(),
                'started_at' => now(), 
                'current_position' => [
                    'skill_ids' => $assignedSkillIds, 
                    'current_skill_index' => $startIndex, 
                    // 'current_level' => $startingLevel, 
                    'completed_skills' => [], 
                    'current_skill_started_at' => null
                    ]
            ]
        );
        
        // Explicitly create the first skill record to match started_at
        ExamAttemptSkill::firstOrCreate(
            [
                'exam_attempt_id' => $attempt->id, 
                'skill_id' => $assignedSkillIds[$startIndex]
            ],
            ['started_at' => $attempt->started_at, 'status' => 'in_progress']
        );

        return $attempt;
    }
}
