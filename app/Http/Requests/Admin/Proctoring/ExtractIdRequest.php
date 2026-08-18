<?php

namespace App\Http\Requests\Admin\Proctoring;

use Illuminate\Foundation\Http\FormRequest;

class ExtractIdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_image' => 'required|string',
        ];
    }
}
