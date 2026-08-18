<?php

namespace App\Http\Requests\Admin\Passage;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePassageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|in:text,image,audio,video',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'media_path' => 'nullable|string',
            'questions_limit' => 'nullable|integer',
            'is_random' => 'nullable|boolean',
        ];
    }
}
