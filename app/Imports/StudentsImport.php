<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\User;
use App\Models\Package;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Row;

class StudentsImport implements OnEachRow, WithHeadingRow, WithValidation
{
    /**
     * Handle each row of the excel import individually
     * This allows for dual-model creation (User + Student) in an atomic transaction
     */
    public function onRow(Row $row)
    {
        $data = $row->toArray();

        // Email is the unique identifier for identities
        if (empty($data['email']) || User::where('email', $data['email'])->exists()) {
            return; 
        }

        DB::transaction(function () use ($data) {
            // 1. Prepare Identity (User)
            $password = $data['password'] ?? Str::random(10);
            $user = User::create([
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'username' => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'religion' => $data['religion'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'password' => Hash::make($password),
                'is_active' => $this->parseBoolean($data['is_active'] ?? true),
                'role' => 'student',
            ]);

            // 2. Fetch Package for auto-skill assignment
            $assignedSkills = [];
            if (!empty($data['package_id'])) {
                $package = Package::find($data['package_id']);
                $assignedSkills = $package ? ($package->skills ?? []) : [];
            }

            // 3. Create Profile (Student)
            $student = Student::create([
                'user_id' => $user->id,
                'student_code' => $data['student_code'] ?? null,
                'partner_id' => $data['partner_id'] ?? null,
                'come_from' => $data['come_from'] ?? null,
                'student_type' => $data['student_type'] ?? null,
                'year_of_arabic' => $data['year_of_arabic'] ?? null,
                'not_adaptive' => isset($data['not_adaptive']) ? (bool)$data['not_adaptive'] : true,
                'package_id' => $data['package_id'] ?? null,
                'exam_type' => $data['exam_type'] ?? 'adult',
                'assigned_skills' => $assignedSkills,
                'registration_source' => 'batch',
                'registration_date' => now(),
            ]);

            // 4. Automated Exam Enrollment & Skill Filtering
            Student::assignDefaultExam($student);
        });
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'exam_type' => 'nullable|in:adult,children',
        ];
    }

    private function parseBoolean($value)
    {
        if (is_null($value)) return true;

        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'yes', 'active']);
    }
}
