<?php

namespace App\Http\Requests\Admin\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'fName_contact' => 'sometimes|nullable|string|max:255',
            'lName_contact' => 'sometimes|nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'sometimes|nullable|string|max:20',
            'password' => 'sometimes|nullable|min:6',
            'partner_name' => 'required|string|max:255',
            'website' => 'sometimes|nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'r_date' => 'nullable|string',
            'is_active' => 'nullable',
            'proctoring_mode' => 'nullable|string|in:none,full,identity_only',
        ];
    }
}
