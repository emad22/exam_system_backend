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

    protected $partnerId;
    protected $packageId;
    protected $globalSkills;
    protected $examCategoryId;

    public function __construct($partnerId = null, $packageId = null, $globalSkills = null, $examCategoryId = null)
    {
        $this->partnerId      = $partnerId;
        $this->packageId      = $packageId;
        $this->globalSkills   = is_array($globalSkills) ? $globalSkills : null;
        $this->examCategoryId = $examCategoryId;
    }

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
                'last_name'  => $data['last_name'] ?? '',
                'username'   => $data['username'],
                'email'      => $data['email'],
                'phone'      => $data['phone'] ?? null,
                'gender'     => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'address'    => $data['address'] ?? null,
                'city'       => $data['city'] ?? null,
                'country'    => $data['country'] ?? null,
                'religion'   => $data['religion'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'password'   => Hash::make($password),
                'is_active'  => $this->parseBoolean($data['is_active'] ?? true),
                'role'       => 'student',
            ]);

            // 2. Resolve assigned skills
            // Priority: form global skills > form package > excel row package
            $assignedSkills = [];

            if (!empty($this->globalSkills)) {
                // Skills override selected manually in the form
                $assignedSkills = $this->globalSkills;
            } elseif (!empty($this->packageId)) {
                // Package selected in the form
                $package        = Package::find($this->packageId);
                $assignedSkills = $package ? ($package->skills ?? []) : [];
            } elseif (!empty($data['package_id'])) {
                // Package specified in the Excel row (fallback)
                $package        = Package::find($data['package_id']);
                $assignedSkills = $package ? ($package->skills ?? []) : [];
            }

            // 3. Resolve exam_category_id
            // Priority: form category > Excel row category > first active category
            $examCategoryId = $this->examCategoryId
                ?? ($data['exam_category_id'] ?? null)
                ?? (\App\Models\ExamCategory::where('is_active', true)->first()->id ?? null);

            // 4. Create Profile (Student)
            $rawCode = $data['student_code'] ?? $data['id_number'] ?? null;
            $studentCode = $rawCode !== null ? trim((string) $rawCode) : '';
            if ($studentCode === '') {
                $studentCode = null;
            }

            $student = Student::create([
                'user_id'             => $user->id,
                'student_code'        => $studentCode,
                'institution_code'    => isset($data['institution_code']) ? (trim((string) $data['institution_code']) ?: null) : null,
                'partner_id'          => $this->partnerId,
                'come_from'           => $data['come_from'] ?? null,
                'student_type'        => $data['student_type'] ?? null,
                'year_of_arabic'      => $data['year_of_arabic'] ?? null,
                'is_continue'         => isset($data['is_continue']) ? (bool) $data['is_continue'] : false,
                'allows_retry'        => isset($data['allows_retry']) ? $this->parseBoolean($data['allows_retry']) : false,
                'package_id'          => $this->packageId ?? ($data['package_id'] ?? null),
                'exam_category_id'    => $examCategoryId,
                'assigned_skills'     => $assignedSkills,
                'registration_source' => 'batch',
                'registration_date'   => now(),
            ]);

            // 5. Automated Exam Enrollment & Skill Filtering
            Student::assignDefaultExam($student, null);
        });
    }

    public function rules(): array
    {
        return [
            'email'            => 'required|email|unique:users,email',
            'username'         => 'nullable|string|unique:users,username',
            'first_name'       => 'required|string',
            'last_name'        => 'required|string',
            'institution_code' => 'nullable|string|max:100',
        ];
    }

    private function parseBoolean($value)
    {
        if (is_null($value)) return true;

        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'yes', 'active']);
    }
}
