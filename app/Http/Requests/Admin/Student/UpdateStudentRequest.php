<?php

namespace App\Http\Requests\Admin\Student;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize empty string codes to null before validation runs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('student_code') && trim((string) $this->input('student_code')) === '') {
            $this->merge(['student_code' => null]);
        }
        if ($this->has('institution_code') && trim((string) $this->input('institution_code')) === '') {
            $this->merge(['institution_code' => null]);
        }
    }

    public function rules(): array
    {
        // Resolve the student's user_id for the unique username rule
        $student  = $this->route('student');
        $userId   = $student?->user_id ?? 0;

        return [
            'first_name'                    => 'sometimes|required|string|max:255',
            'last_name'                     => 'sometimes|required|string|max:255',
            'email'                         => 'sometimes|nullable|email',
            'phone'                         => 'sometimes|nullable|string|max:20',
            'gender'                        => 'sometimes|nullable|in:male,female,other',
            'birth_date'                    => 'sometimes|nullable|date',
            'address'                       => 'sometimes|nullable|string|max:255',
            'city'                          => 'sometimes|nullable|string|max:255',
            'country'                       => 'sometimes|nullable|string|max:255',
            'religion'                      => 'sometimes|nullable|string|max:255',
            'occupation'                    => 'sometimes|nullable|string|max:255',
            'student_code'                  => 'sometimes|nullable|string|max:50',
            'institution_code'              => 'sometimes|nullable|string|max:100',
            'come_from'                     => 'sometimes|nullable|string|max:255',
            'student_type'                  => 'sometimes|nullable|string|max:50',
            'year_of_arabic'                => 'sometimes|nullable|integer',
            'is_continue'                   => 'sometimes|nullable|boolean',
            'allows_retry'                  => 'sometimes|boolean',
            'is_active'                     => 'sometimes|boolean',
            'is_demo'                       => 'sometimes|boolean',
            'is_demo_proctored'             => 'sometimes|boolean',
            'bypass_identity_verification'  => 'sometimes|boolean',
            'package_id'                    => 'sometimes|nullable|exists:packages,id',
            'exam_category_id'              => 'sometimes|required|exists:exam_categories,id',
            'assigned_skills'               => 'sometimes|array',
            'assigned_skills.*'             => 'nullable',
            'partner_id'                    => 'sometimes|nullable|exists:partners,id',
            'username'                      => "sometimes|required|string|max:255|unique:users,username,{$userId}",
            'password'                      => 'sometimes|nullable|string|min:6',
        ];
    }
}
