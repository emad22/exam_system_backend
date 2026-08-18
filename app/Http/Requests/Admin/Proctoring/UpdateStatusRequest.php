<?php

namespace App\Http\Requests\Admin\Proctoring;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:active,paused,ended,cancelled',
            'final_verdict' => 'nullable|in:pass,fail,review_required',
        ];
    }
}
