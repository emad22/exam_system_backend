<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'student_id'            => $this->student_id,
            'exam_attempt_id'       => $this->exam_attempt_id,
            'template_id'           => $this->template_id,
            'certificate_number'    => $this->certificate_number,
            'score'                 => $this->score,
            'issue_date'            => $this->issue_date?->format('Y-m-d H:i:s'),
            'verification_code'     => $this->verification_code,
            'verification_url'      => url('/verify-certificate/' . $this->verification_code),
            'download_url'          => url('/api/v1/certificates/' . $this->id . '/download'),
            'is_visible_to_student' => (bool) $this->is_visible_to_student,
            'created_at'            => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'            => $this->updated_at?->format('Y-m-d H:i:s'),

            // Relations
            'student'               => $this->whenLoaded('student', fn() => new StudentResource($this->student)),
            'attempt'               => $this->whenLoaded('attempt', fn() => new ExamAttemptResource($this->attempt)),
            'template'              => $this->whenLoaded('template', fn() => [
                'id'    => $this->template->id,
                'title' => $this->template->title,
            ]),
        ];
    }
}
