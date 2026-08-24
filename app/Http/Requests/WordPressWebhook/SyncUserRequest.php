<?php

namespace App\Http\Requests\WordPressWebhook;

use Illuminate\Foundation\Http\FormRequest;

class SyncUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'nullable|string|unique:users,username',
            'email' => 'nullable|email',
        ];
    }
}
