<?php

namespace App\Http\Requests\Admin\Proctoring;

use Illuminate\Foundation\Http\FormRequest;

class InitiateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_attempt_id' => 'nullable|exists:exam_attempts,id',
            'session_id' => 'nullable|exists:proctoring_sessions,id',
        ];
    }
}
