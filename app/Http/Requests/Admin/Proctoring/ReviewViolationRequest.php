<?php

namespace App\Http\Requests\Admin\Proctoring;

use Illuminate\Foundation\Http\FormRequest;

class ReviewViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:confirmed,dismissed,suspicious',
            'proctor_notes' => 'nullable|string|max:1000',
            'action_taken' => 'nullable|in:warning,pause_exam,terminate_exam,report_to_instructor',
        ];
    }
}
