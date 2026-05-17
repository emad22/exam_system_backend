<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\ExamAttempt;
use Carbon\Carbon;

class PartnerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $partnerId = $request->user()->partner->id ?? null;

        if (!$partnerId) {
            return response()->json(['message' => 'Partner profile not found.'], 404);
        }

        $studentIds = Student::where('partner_id', $partnerId)->pluck('id');

        $totalStudents = $studentIds->count();
        
        $totalAttempts = ExamAttempt::whereIn('student_id', $studentIds)->count();
        $completedAttempts = ExamAttempt::whereIn('student_id', $studentIds)->where('status', 'completed')->count();
        $pendingAttempts = ExamAttempt::whereIn('student_id', $studentIds)->where('status', '!=', 'completed')->count();


        $recentAttempts = ExamAttempt::with(['student:id,user_id,partner_id', 'student.user:id,first_name,last_name', 'exam:id,title','attemptSkills.skill' => function ($query) {
            $query->withCount('levels');
        }])
            ->whereIn('student_id', $studentIds)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'student_name' => $attempt->student->user ? ($attempt->student->user->first_name . ' ' . $attempt->student->user->last_name) : 'Unknown',
                    'exam_title' => $attempt->exam->title ?? 'Unknown',
                    'status' => $attempt->status,
                    // 'total_score' => round($attempt->overall_score ?? 0, 1),
                    'total_score' => $this->getTotalScore($attempt),
                    'avg_score' => round($attempt->overall_score ?? 0, 1), 
                    'created_at' => $attempt->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'data' => [
                'stats' => [
                    'students' => [
                        'total' => $totalStudents,
                        'today' => Student::where('partner_id', $partnerId)->whereDate('created_at', Carbon::today())->count(),
                    ],
                    'exams' => [
                        'total' => ExamAttempt::whereIn('student_id', $studentIds)->distinct('exam_id')->count('exam_id'),
                        'today' => ExamAttempt::whereIn('student_id', $studentIds)->whereDate('created_at', Carbon::today())->distinct('exam_id')->count('exam_id'),
                    ],
                    'attempts' => [
                        'total' => $totalAttempts,
                        'completed' => $completedAttempts,
                        'pending' => $pendingAttempts,
                        'last_7_days' => ExamAttempt::whereIn('student_id', $studentIds)->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
                    ],
                    'live' => ExamAttempt::whereIn('student_id', $studentIds)->where('status', 'ongoing')->count(),
                ],
                'recent_attempts' => $recentAttempts
            ]
        ]);
    }

    private function getCalculatedSkillScore($skillResult)
    {
        if (!$skillResult || $skillResult->score === null) {
            return 0;
        }
        //Logger ("skill name ".$skillResult->skill);
        $levelsCount = $skillResult->skill->levels_count ?? 1;
        Logger("in getCalculatedSkillScore levels count " . $levelsCount . " total.... " . round((float)$skillResult->score * $levelsCount));
        return round((float)$skillResult->score * $levelsCount);
    }

    private function getTotalScore($attempt)
    {
        if (!$attempt || !$attempt->attemptSkills) {
            return 0;
        }
        
       
          $currentPos = $attempt-> current_position;
        //logger("*************** current position ".$currentPos);
            if (is_string($currentPos)) {
                Logger ("is string ****************");
            $currentPos = json_decode($currentPos, true);
        }
              
        $skills_count = count($currentPos['skill_ids'] ?? []);
        // Logger($currentPos);
        // logger("*************** current position ". "*************** skill count ".$skills_count);

        return $attempt->attemptSkills
            ->filter(function ($skillResult) {
                $name = strtolower($skillResult->skill->name ?? '');

                return str_contains($name, 'read')
                    || str_contains($name, 'listen')
                    || str_contains($name, 'struct');
            })
            ->reduce(function ($sum, $skillResult) use ($skills_count) {
              //   Logger("in ///////////////////////// getTotalScore " . ($sum + $this->getCalculatedSkillScore($skillResult))/3);
                return round( $sum + ( $this->getCalculatedSkillScore($skillResult) / $skills_count));
            }, 0);
    }

}
