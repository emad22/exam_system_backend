<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                              => $this->id,
            'student_code'                    => $this->student_code,
            'institution_code'                => $this->institution_code,
            'come_from'                       => $this->come_from,
            'registration_date'               => $this->registration_date,
            'student_type'                    => $this->student_type,
            'year_of_arabic'                  => $this->year_of_arabic,
            'is_continue'                     => $this->is_continue,
            'num_of_login'                    => $this->num_of_login,
            'package_id'                      => $this->package_id,
            'exam_category_id'                => $this->exam_category_id,
            'assigned_skills'                 => $this->assigned_skills,
            'registration_source'             => $this->registration_source,
            'partner_id'                      => $this->partner_id,
            'allows_retry'                    => $this->allows_retry,
            'is_demo'                         => $this->is_demo,
            'is_demo_proctored'               => $this->is_demo_proctored,
            'bypass_identity_verification'    => $this->bypass_identity_verification,
            'from_promotion'                  => $this->from_promotion,
            'created_at'                      => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'                      => $this->updated_at?->format('Y-m-d H:i:s'),
            'attempts_count'                  => $this->when(
                isset($this->attempts_count),
                $this->attempts_count
            ),
            'completed_attempts_count'        => $this->when(
                isset($this->completed_attempts_count),
                $this->completed_attempts_count
            ),
            'in_progress_attempts_count'      => $this->when(
                isset($this->in_progress_attempts_count),
                $this->in_progress_attempts_count
            ),

            // Relations — only included when loaded
            'user'    => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'package' => $this->whenLoaded('package', fn() => [
                'id'   => $this->package->id,
                'name' => $this->package->name,
            ]),
            'category' => $this->whenLoaded('category', fn() => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'attempts' => $this->whenLoaded('attempts'),
        ];
    }
}
