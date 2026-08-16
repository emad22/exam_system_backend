<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\Skill;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\ExamSkill;

class PartnerReportController extends Controller
{
    /**
     * Get reports (For Partner)
     */
    public function index(Request $request)
    {
        $partnerId = $request->user()->partner->id ?? null;

        if (!$partnerId) {
            return response()->json(['message' => 'Partner profile not found.'], 404);
        }

        $studentIds = Student::where('partner_id', $partnerId)->pluck('id');

        $attempts = ExamAttempt::with(['student.user', 'user', 'exam', 'attemptSkills.skill' => function ($query) {
            $query->withCount('levels');
        }])
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['completed', 'ongoing', 'paused'])
            ->orderBy('updated_at', 'desc')
            ->paginate($request->input('per_page', 500));

        // to get available skills for each ExamAttempt
        $attempts->getCollection()->transform(function ($attempt) {
            $currentPos = $attempt->current_position;
            if (is_string($currentPos)) {
                $currentPos = json_decode($currentPos, true);
            }
            $attempt->skills_count = count($currentPos['skill_ids'] ?? []);
            $skillIds = $currentPos['skill_ids'] ?? [];

            $attempt->total_levels = Skill::whereIn('id', $skillIds)
                ->withCount('levels')
                ->get()
                ->sum('levels_count');

            $examSkills = ExamSkill::where('exam_id', $attempt->exam_id)
                ->get()
                ->keyBy('skill_id');

            foreach ($attempt->attemptSkills as $attemptSkill) {
                $examSkill = $examSkills->get($attemptSkill->skill_id);
                $attemptSkill->max_points = $examSkill?->max_points;
            }

            $cefr = app(\App\Services\CertificateService::class)->mapToCefr($attempt->overall_score ?? 0, 'core');
            $actfl = app(\App\Services\CertificateService::class)->mapToActfl($attempt->overall_score ?? 0, 'core');
            $attempt->efficiency_level = $cefr;
            $attempt->cefr_level = $cefr;
            $attempt->actfl_level = $actfl;
            $attempt->cefr_actfl_level = "{$cefr} / {$actfl}";

            return $attempt;
        });

        return response()->json($attempts);
    }

    /**
     * Get detailed movement report for a specific attempt
     */
    public function show(Request $request, ExamAttempt $attempt)
    {
        $partnerId = $request->user()->partner->id ?? null;

        // Security Check: Verify attempt belongs to one of the partner's students
        if (!$partnerId || $attempt->student->partner_id !== $partnerId) {
            return response()->json(['message' => 'Unauthorized access to this report.'], 403);
        }

        $attempt->load([
            'student.user',
            'user',
            'exam',
            'attemptSkills.skill' => function ($q) {
                $q->withCount('levels');
            },
            'attemptLevels' => function ($q) {
                $q->orderBy('created_at', 'asc');
            },
            'answers' => function ($q) {
                $q->with([
                    'question' => function ($sq) {
                        $sq->with(['passage', 'options', 'skill']);
                    },
                    'option'
                ])->orderBy('created_at', 'asc');
            }
        ]);

        $currentPos = $attempt->current_position;
        if (is_string($currentPos)) {
            $currentPos = json_decode($currentPos, true);
        }
        $attempt->skills_count = count($currentPos['skill_ids'] ?? []);

        $skillIds = $currentPos['skill_ids'] ?? [];

        $attempt->total_levels = Skill::whereIn('id', $skillIds)
            ->withCount('levels')
            ->get()
            ->sum('levels_count');

        $examSkills = ExamSkill::where('exam_id', $attempt->exam_id)
            ->get()
            ->keyBy('skill_id');

        foreach ($attempt->attemptSkills as $attemptSkill) {
            $examSkill = $examSkills->get($attemptSkill->skill_id);
            $attemptSkill->max_points = $examSkill?->max_points;
        }

        $cefr = app(\App\Services\CertificateService::class)->mapToCefr($attempt->overall_score ?? 0, 'core');
        $actfl = app(\App\Services\CertificateService::class)->mapToActfl($attempt->overall_score ?? 0, 'core');
        $attempt->efficiency_level = $cefr;
        $attempt->cefr_level = $cefr;
        $attempt->actfl_level = $actfl;
        $attempt->cefr_actfl_level = "{$cefr} / {$actfl}";

        return response()->json($attempt);
    }
}
