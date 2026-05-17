<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => [
                'students' => [
                    'total' => $this['students_count'],
                    'today' => $this['students_today'],
                ],
                'exams' => [
                    'total' => $this['exams_count'],
                    'today' => $this['exams_today'],
                ],
                'attempts' => [
                    'total' => $this['attempts_count'],
                    'last_7_days' => $this['attempts_last_7_days'],
                ],
                'live' => $this['live_students_count'],
            ],
            'recent_attempts' => $this['recent_attempts']->map(function($attempt) {
                return [
                    'id' => $attempt->id,
                    'student_name' => trim(($attempt->student?->user?->first_name ?? '') . ' ' . ($attempt->student?->user?->last_name ?? '')) ?: 'Unknown',
                    'exam_title' => $attempt->exam?->title ?? 'Deleted Exam',
                    //'total_score' => $attempt->attempt_skills_sum_score ?? 0,
                    'total_score' => $this->getTotalScore($attempt),
                    'avg_score' => round($attempt->attempt_skills_avg_score ?? 0, 1),
                    'status' => $attempt->status,
                    'created_at' => $attempt->created_at->diffForHumans(),
                ];
            }),
        ];
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
        // $currentPos = $attempt-> current_position;
        // logger("*************** current position ".json_encode($attempt->current_pos));
       
          $currentPos = $attempt-> current_position;
        //logger("*************** current position ".$currentPos);
            if (is_string($currentPos)) {
              //  Logger ("is string ****************");
            $currentPos = json_decode($currentPos, true);
        }
              
        $skills_count = count($currentPos['skill_ids'] ?? []);
        //  Logger($currentPos);
        //  logger("*************** current position ". "*************** skill count ".$skills_count);

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
