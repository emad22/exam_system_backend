<?php

namespace App\Http\Requests\ExamImport;

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
            'files.*' => 'required|file',
            'paths' => 'required|array',
        ];
    }
}
