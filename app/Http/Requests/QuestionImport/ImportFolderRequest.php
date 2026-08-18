<?php

namespace App\Http\Requests\QuestionImport;

use Illuminate\Foundation\Http\FormRequest;

class ImportFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => 'required|array',
            'files.*.examName' => 'required|string',
            'files.*.skillName' => 'required|string',
            'files.*.fileName' => 'required|string',
            'files.*.content' => 'present|string|nullable',
        ];
    }
}
