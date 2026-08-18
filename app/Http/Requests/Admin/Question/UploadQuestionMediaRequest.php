<?php

namespace App\Http\Requests\Admin\Question;

use Illuminate\Foundation\Http\FormRequest;

class UploadQuestionMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:mp3,wav,ogg,m4a,jpeg,png,jpg,gif,svg,mp4,webm|max:10240',
        ];
    }
}
