<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSkill;
use App\Models\ExamAttemptLevel;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;
use App\Models\Skill;

class ReportController extends Controller
{
    /**
     * Get reports (For Supervisor and Admin)
     */
   

     public function index(Request $request)
    {
         $partnerId = auth()->id();
         
        $attempts = ExamAttempt::with(['student.user', 'user', 'exam', 'attemptSkills.skill' => function ($query) {
            $query->withCount('levels');
        }])
            ->whereIn('status', ['completed', 'ongoing'])
            ->whereHas('student', function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId)
                      ->where('role', 'student');
            })

            ->orderBy('updated_at', 'desc')
            ->paginate(30);

        //to get avialable skills for each ExamAttemp

        $attempts->getCollection()->transform(function ($attempt) {
        $currentPos = $attempt-> current_position;
       //logger("*************** current position ".json_encode($attempt->current_pos));
        if (is_string($currentPos)) {
            $currentPos = json_decode($currentPos, true);
        }
        $attempt->skills_count = count($currentPos['skill_ids'] ?? []);
        $skillIds = $currentPos['skill_ids'] ?? [];
   
        $attempt->total_levels = Skill::whereIn('id', $skillIds)
            ->withCount('levels')
            ->get()
            ->sum('levels_count');

        return $attempt;
     });
        return response()->json($attempts);
    }


    //  public function index()
    // {
    //     $partnerId = auth()->id();

    //     $reports = ExamAttempt::query()
    //         ->whereHas('student', function ($q) use ($partnerId) {
    //             $q->where('partner_id', $partnerId)
    //                   ->where('role', 'student');
    //         })
    //         ->with([
    //             'student',
    //             'exam',
    //             'attemptSkills.skill'
    //         ])
    //         ->latest()
    //         ->paginate(10);

    //     return response()->json($reports);
    // }


   

    /**
     * Get detailed movement report for a specific attempt
     */
    public function show(ExamAttempt $attempt)
    {

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

   
        $currentPos = $attempt-> current_position;
       //logger("*************** current position ".json_encode($attempt->current_pos));   
        if (is_string($currentPos)) {
            $currentPos = json_decode($currentPos, true);
        }
        $attempt->skills_count = count($currentPos['skill_ids'] ?? []);
       // $attempt->skill_ids = $currentPos['skill_ids'] ?? [];

        $skillIds = $currentPos['skill_ids'] ?? [];
   
        $attempt->total_levels = Skill::whereIn('id', $skillIds)
            ->withCount('levels')
            ->get()
            ->sum('levels_count');


        return response()->json($attempt);
    }

    $stats = [
    'students_count' => User::where('role', 'student')
        ->where('partner_id', auth()->id())
        ->count(),

    'attempts_count' => ExamAttempt::whereIn('student_id', $studentIds)->count(),

    'avg_score' => ExamAttempt::whereIn('student_id', $studentIds)
        ->avg('overall_score'),
];

}
