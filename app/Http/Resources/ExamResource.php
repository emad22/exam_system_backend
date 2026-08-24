<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'title'                   => $this->title,
            'description'             => $this->description,
            'exam_category_id'        => $this->exam_category_id,
            'language_id'             => $this->language_id,
            'passing_score'           => $this->passing_score,
            'timer_type'              => $this->timer_type,
            'time_limit'              => $this->time_limit,
            'is_active'               => (bool) $this->is_active,
            'is_default'              => (bool) $this->is_default,
            'as_demo'                 => (bool) $this->as_demo,
            'play_in_real_player'     => (bool) $this->play_in_real_player,
            'default_want_reading'    => (bool) $this->default_want_reading,
            'default_want_listening'  => (bool) $this->default_want_listening,
            'default_want_grammar'    => (bool) $this->default_want_grammar,
            'default_want_writing'    => (bool) $this->default_want_writing,
            'default_want_speaking'   => (bool) $this->default_want_speaking,
            'created_at'              => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'              => $this->updated_at?->format('Y-m-d H:i:s'),

            // Counts (only included when loaded via withCount)
            'attempts_count'          => $this->whenCounted('attempts'),
            'questions_count'         => $this->whenCounted('questions'),
            'skills_count'            => $this->whenCounted('skills'),

            // Breakdown per skill (set manually on the model in index)
            'breakdown'               => $this->when(isset($this->breakdown), $this->breakdown),

            // Relations
            'category'                => $this->whenLoaded('category', fn() => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'language'                => $this->whenLoaded('language', fn() => [
                'id'   => $this->language->id,
                'name' => $this->language->name,
            ]),
            'skills'                  => $this->whenLoaded('skills', fn() =>
                $this->skills->map(fn($skill) => [
                    'id'         => $skill->id,
                    'name'       => $skill->name,
                    'short_code' => $skill->short_code,
                    'duration'   => $skill->pivot->duration ?? null,
                    'is_optional' => (bool) ($skill->pivot->is_optional ?? false),
                    'max_points' => $skill->pivot->max_points ?? 0,
                ])
            ),
            'questionRules'           => $this->whenLoaded('questionRules'),
            'questions'               => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
