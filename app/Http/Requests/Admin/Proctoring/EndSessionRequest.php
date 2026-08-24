<?php

namespace App\Http\Requests\Admin\Proctoring;

use Illuminate\Foundation\Http\FormRequest;

class EndSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'close_reason' => 'required|in:exam_submitted,time_ended,terminated_by_proctor,connection_lost,student_left',
        ];
    }
}
