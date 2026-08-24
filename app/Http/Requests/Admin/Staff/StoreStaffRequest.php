<?php

namespace App\Http\Requests\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'nullable|string|email|max:255',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,teacher,supervisor,demo,partner',
            'is_active' => 'sometimes|boolean',
            'phone' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'partner_name' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'proctoring_required' => 'sometimes|boolean',
            'proctoring_mode' => 'sometimes|string|in:none,full,identity_only',
        ];
    }
}
