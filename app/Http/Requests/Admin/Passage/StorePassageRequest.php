<?php

namespace App\Http\Requests\Admin\Passage;

use Illuminate\Foundation\Http\FormRequest;

class StorePassageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:text,image,audio,video',
            'title' => 'nullable|string|max:255',
            'content' => 'required_if:type,text|nullable|string',
            'media_path' => 'nullable|string',
            'questions_limit' => 'nullable|integer',
            'is_random' => 'nullable|boolean',
        ];
    }
}
