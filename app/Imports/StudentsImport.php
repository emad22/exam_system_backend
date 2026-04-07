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

        // Check if user already exists by email to avoid fatal crashes during import
        if (User::where('email', $data['email'])->exists()) {
            return; 
        }

        DB::transaction(function () use ($data) {
            // 1. Prepare Identity (User)
            $password = $data['password'] ?? Str::random(10);
            $user = User::create([
                'name' => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''),
                'email' => $data['email'],
                'password' => Hash::make($password),
                'role' => 'student',
            ]);

            // 2. Fetch Package for auto-skill assignment
            $assignedSkills = [];
            if (!empty($data['package_id'])) {
                $package = Package::find($data['package_id']);
                $assignedSkills = $package ? ($package->skills ?? []) : [];
            }

            // 3. Create Profile (Student)
            Student::create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'] ?? '',
                'last_name' => $data['last_name'] ?? '',
                'phone' => $data['phone'] ?? null,
                'gender' => $data['gender'] ?? null,
                'package_id' => $data['package_id'] ?? null,
                'exam_type' => $data['exam_type'] ?? 'adult',
                'assigned_skills' => $assignedSkills,
                'registration_source' => 'batch',
            ]);
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
}
