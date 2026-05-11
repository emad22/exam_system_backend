<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;

class ExamResultController extends Controller
{
    public function results(ExamAttempt $attempt)
    {
        $this->authorize('view', $attempt);
        $attempt->load(['attemptSkills.skill']);
        $results = $attempt->attemptSkills->map(fn($as) => [
            'name' => $as->skill->name,
            'level' => $as->max_level_reached,
            'score' => $as->score,
        ]);
        return response()->json(['skill_results' => $results]);
    }
}
