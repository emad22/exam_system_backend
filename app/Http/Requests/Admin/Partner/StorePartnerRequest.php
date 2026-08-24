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
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string|max:20',
            'password' => 'required|min:6',
            'partner_name' => 'required|string',
            'website' => 'sometimes|nullable|string',
            'country' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'r_date' => 'nullable|string',
        ];
    }
}
