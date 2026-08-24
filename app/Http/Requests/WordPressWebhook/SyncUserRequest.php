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
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'package_id' => 'required',
            'wp_user_id' => 'required|string',
            'phone' => 'required|string',
            'address' => 'nullable|string',
            'country' => 'nullable|string',
            'exam_category_id' => 'nullable|exists:exam_categories,id',
            'exam_type' => 'nullable|string', // Support legacy WP slug (adult/children)
        ];
    }
}
