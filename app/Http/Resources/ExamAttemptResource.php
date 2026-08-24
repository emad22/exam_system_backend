<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,
            'student_id'     => $this->student_id,
            'exam_id'        => $this->exam_id,
            'status'         => $this->status,
            'overall_score'  => $this->overall_score,
            'started_at'     => $this->started_at?->format('Y-m-d H:i:s'),
            'finished_at'    => $this->finished_at?->format('Y-m-d H:i:s'),
            'created_at'     => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'     => $this->updated_at?->format('Y-m-d H:i:s'),

            // Aggregated / computed attributes if set
            'total_score'    => $this->when(isset($this->total_score), $this->total_score),
            'max_possible'   => $this->when(isset($this->max_possible), $this->max_possible),
            'score_display'  => $this->when(isset($this->score_display), $this->score_display),
            'outcome_text'   => $this->when(isset($this->outcome_text), $this->outcome_text),
            'last_activity'  => $this->when(isset($this->last_activity), $this->last_activity),
            'recent_answers' => $this->when(isset($this->recent_answers), $this->recent_answers),

            // Relations
            'student'        => $this->whenLoaded('student', fn() => new StudentResource($this->student)),
            'user'           => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'exam'           => $this->whenLoaded('exam', fn() => [
                'id'          => $this->exam->id,
                'title'       => $this->exam->title ?? $this->exam->name,
                'description' => $this->exam->description,
            ]),
            'certificate'    => $this->whenLoaded('certificate'),
            'attemptSkills'  => $this->whenLoaded('attemptSkills'),
            'attemptLevels'  => $this->whenLoaded('attemptLevels'),
        ];
    }
}
