<?php

namespace App\Http\Requests\Admin\Student;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
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
        return [
            'first_name'                    => 'required|string|max:255',
            'last_name'                     => 'required|string|max:255',
            'email'                         => 'nullable|string|email|max:255',
            'phone'                         => 'nullable|string|max:20',
            'gender'                        => 'nullable|in:male,female,other',
            'birth_date'                    => 'nullable|date',
            'address'                       => 'nullable|string|max:255',
            'city'                          => 'nullable|string|max:255',
            'country'                       => 'nullable|string|max:255',
            'religion'                      => 'nullable|string|max:255',
            'occupation'                    => 'nullable|string|max:255',
            'student_code'                  => 'nullable|string|max:50',
            'institution_code'              => 'nullable|string|max:100',
            'come_from'                     => 'nullable|string|max:255',
            'student_type'                  => 'nullable|string|max:50',
            'year_of_arabic'                => 'nullable|integer',
            'is_continue'                   => 'sometimes|boolean',
            'allows_retry'                  => 'sometimes|boolean',
            'is_demo'                       => 'sometimes|boolean',
            'is_demo_proctored'             => 'sometimes|boolean',
            'bypass_identity_verification'  => 'sometimes|boolean',
            'exam_id'                       => 'nullable|exists:exams,id',
            'exam_category_id'              => 'nullable|exists:exam_categories,id',
            'assigned_skills'               => 'nullable|array',
            'assigned_skills.*'             => 'nullable',
            'package_id'                    => 'nullable|exists:packages,id',
            'partner_id'                    => 'nullable|exists:partners,id',
            'username'                      => 'required|string|max:255|unique:users',
            'password'                      => 'required|string|min:6',
        ];
    }
}
