<?php

namespace App\Http\Requests\Admin\Question;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateQuestionLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_ids'    => 'required|array',
            'question_ids.*'  => 'exists:questions,id',
            'level_id'        => 'required|integer|min:1|max:9',
        ];
    }
}
