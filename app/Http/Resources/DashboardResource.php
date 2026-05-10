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
                    'total_score' => $attempt->attempt_skills_sum_score ?? 0,
                    'avg_score' => round($attempt->attempt_skills_avg_score ?? 0, 1),
                    'status' => $attempt->status,
                    'created_at' => $attempt->created_at->diffForHumans(),
                ];
            }),
        ];
    }
}
