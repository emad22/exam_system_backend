<?php

namespace App\Http\Requests\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id ?? null;

        return [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $userId,
            'email' => 'sometimes|nullable|email',
            'role' => 'sometimes|required|in:admin,teacher,supervisor,demo,partner',
            'password' => 'sometimes|nullable|string|min:6',
            'is_active' => 'sometimes|boolean',
            'phone' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
            'partner_name' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|string|max:255',
            'note' => 'sometimes|nullable|string',
            'proctoring_required' => 'sometimes|boolean',
            'proctoring_mode' => 'sometimes|string|in:none,full,identity_only',
        ];
    }
}
