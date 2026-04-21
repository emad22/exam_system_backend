<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\Package;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StudentsImport;
use App\Exports\StudentSkillsExport;
use App\Imports\StudentSkillsImport;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    /**
     * Get all students with their basic stats
     */
    public function index(Request $request)
    {
        $students = Student::with(['user', 'package'])->withCount('attempts')->paginate(30);
        return response()->json($students);
    }

    /**
     * Store new student (Phase 5)
     */
    public function store(Request $request)
    {
        // dd($request->all());
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'religion' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',

            'student_code' => 'nullable|string|max:50|unique:students',
            'come_from' => 'nullable|string|max:255',
            'student_type' => 'nullable|string|max:50',
            'year_of_arabic' => 'nullable|integer',
            'not_adaptive' => 'nullable|boolean',


            'exam_id' => 'nullable|exists:exams,id',
            'exam_category_id' => 'nullable|exists:exam_categories,id',
            'assigned_skills' => 'nullable|array',
            'assigned_skills.*' => 'nullable',
            'package_id' => 'nullable|exists:packages,id',
            'partner_id' => 'nullable|exists:partners,id',
            'password' => 'required|string|min:6',
        ]);

        $assignedSkills = [];
        if (!empty($validated['assigned_skills'])) {
            $assignedSkills = Skill::whereIn('id', $validated['assigned_skills'])
                ->orWhereIn('short_code', $validated['assigned_skills'])
                ->pluck('short_code')
                ->map(fn($code) => strtoupper($code))
                ->unique()
                ->toArray();
        }

        // 1. Create Identity (User)
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $validated['first_name'] . ' ' . $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'birth_date' => !empty($validated['birth_date']) ? \Carbon\Carbon::parse($validated['birth_date'])->toDateString() : null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'religion' => $validated['religion'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => 'student',
        ]);

        // 2. Fetch Package Skills if assigned_skills not provided
        if (empty($assignedSkills) && !empty($validated['package_id'])) {
            $package = Package::find($validated['package_id']);
            $assignedSkills = $package ? ($package->skills ?? []) : [];
        }

        // 3. Resolve Category if missing
        $examCategoryId = $validated['exam_category_id'] ?? null;
        if (!$examCategoryId && !empty($validated['package_id'])) {
            $package = Package::with(['exam'])->find($validated['package_id']);
            if ($package && $package->exam) {
                $examCategoryId = $package->exam->exam_category_id;
            }
        }

        if (!$examCategoryId) {
            $examCategoryId = \App\Models\ExamCategory::where('is_active', true)->first()->id ?? null;
        }

        // 4. Create Profile (Student)
        $student = Student::create([
            'user_id' => $user->id,
            'student_code' => $validated['student_code'] ?? null,
            'come_from' => $validated['come_from'] ?? null,
            'student_type' => $validated['student_type'] ?? null,
            'year_of_arabic' => $validated['year_of_arabic'] ?? null,
            'not_adaptive' => $validated['not_adaptive'] ?? true,
            'package_id' => $validated['package_id'] ?? null,
            'exam_category_id' => $examCategoryId,
            'assigned_skills' => $assignedSkills,
            'partner_id' => $validated['partner_id'] ?? null,
            'registration_source' => 'manual',
            'registration_date' => now(),
        ]);

        // 4. Automated Exam Enrollment & Skill Filtering
        Student::assignDefaultExam($student, $validated['exam_id'] ?? null);

        return response()->json([
            'message' => 'Student account created and Exam assigned successfully.',
            'student' => $student->load(['user', 'package', 'configs.exam'])
        ], 201);
    }

    /**
     * Update student details (Phase 7 - Modal)
     */
    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . ($student->user_id ?? 0),
            'phone' => 'sometimes|nullable|string|max:20',
            'gender' => 'sometimes|nullable|in:male,female,other',
            'birth_date' => 'sometimes|nullable|date',
            'address' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
            'religion' => 'sometimes|nullable|string|max:255',
            'occupation' => 'sometimes|nullable|string|max:255',
            'student_code' => 'sometimes|nullable|string|max:50|unique:students,student_code,' . $student->id,
            'come_from' => 'sometimes|nullable|string|max:255',
            'student_type' => 'sometimes|nullable|string|max:50',
            'year_of_arabic' => 'sometimes|nullable|integer',
            'not_adaptive' => 'sometimes|nullable|boolean',
            'is_active' => 'sometimes|boolean',
            'package_id' => 'sometimes|nullable|exists:packages,id',
            'exam_category_id' => 'sometimes|required|exists:exam_categories,id',
            'assigned_skills' => 'sometimes|array',
            'assigned_skills.*' => 'nullable',
            'partner_id' => 'sometimes|nullable|exists:partners,id',
            'password' => 'sometimes|nullable|string|min:6',
        ]);

        if (isset($validated['assigned_skills'])) {
            $validated['assigned_skills'] = Skill::whereIn('id', $validated['assigned_skills'])
                ->orWhereIn('short_code', $validated['assigned_skills'])
                ->pluck('short_code')
                ->map(fn($code) => strtoupper($code))
                ->unique()
                ->toArray();
        }

        // 1. Update Profile (Student)
        $studentUpdate = $request->only([
            'package_id', 'exam_category_id', 'student_type', 'student_code',
            'come_from', 'year_of_arabic', 'not_adaptive', 'partner_id'
        ]);

        if (isset($validated['assigned_skills'])) {
            $studentUpdate['assigned_skills'] = $validated['assigned_skills'];
        }

        $student->update($studentUpdate);

        // 2. Update Identity (User)
        if ($student->user_id) {
            $user = User::find($student->user_id);
            if ($user) {
                $userUpdate = $request->only([
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'gender',
                    'address',
                    'city',
                    'country',
                    'religion',
                    'occupation',
                    'is_active'
                ]);

                if (!empty($validated['birth_date'])) {
                    $userUpdate['birth_date'] = \Carbon\Carbon::parse($validated['birth_date'])->toDateString();
                }

                if (isset($validated['first_name']) || isset($validated['last_name'])) {
                    $userUpdate['username'] = ($validated['first_name'] ?? $user->first_name) . ' ' . ($validated['last_name'] ?? $user->last_name);
                }

                if (!empty($validated['password'])) {
                    $userUpdate['password'] = Hash::make($validated['password']);
                }

                if (!empty($userUpdate)) {
                    $user->update($userUpdate);
                }
            }
        }

        return response()->json([
            'message' => 'Student and User profile updated successfully.',
            'student' => $student->load(['user', 'package'])
        ]);
    }

    /**
     * Display a specific student.
     */
    public function show(Student $student)
    {
        return response()->json($student->load([
            'user',
            'package',
            'category',
            'attempts.exam',
            'attempts.attemptSkills.skill',
            'attempts.attemptLevels' => function ($q) {
                $q->orderBy('created_at', 'asc');
            }
        ]));
    }

    /**
     * Remove a student (deletes user identity).
     */
    public function destroy(Student $student)
    {
        $userId = $student->user_id;
        // Delete the student profile first
        $student->delete();

        // Then explicitly delete the associated user to ensure both are removed
        if ($userId) {
            User::destroy($userId);
        }
        return response()->json(['message' => 'Student record deleted successfully.']);
    }

    /**
     * Remove multiple students.
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:students,id',
        ]);

        $students = Student::whereIn('id', $request->ids)->get();
        $userIds = [];

        foreach ($students as $student) {
            if ($student->user_id) {
                $userIds[] = $student->user_id;
            }
            $student->delete();
        }

        if (!empty($userIds)) {
            User::destroy($userIds);
        }

        return response()->json(['message' => 'Selected student records deleted successfully.']);
    }

    /**
     * Batch import students from Excel (Phase 6)
     */
    public function batchImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls',
            'partner_id' => 'nullable',
            'package_id' => 'nullable|exists:packages,id',
            'assigned_skills' => 'nullable' // JSON encoded or array
        ]);

        try {
            $assignedSkills = $request->input('assigned_skills');
            if (is_string($assignedSkills)) {
                $assignedSkills = json_decode($assignedSkills, true);
            }

            Excel::import(
                new StudentsImport(
                    $request->input('partner_id'),
                    $request->input('package_id'),
                    $assignedSkills
                ),
                $request->file('file')
            );
            return response()->json(['message' => 'Students imported successfully.']);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
            }
            return response()->json(['message' => 'Import failed.', 'errors' => $errors], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Batch update assigned skills for multiple students by email.
     */
    public function bulkUpdateSkills(Request $request)
    {
        $request->validate([
            'emails' => 'required|array',
            'emails.*' => 'email',
            'skills' => 'required|array',
            'skills.*' => 'nullable',
        ]);

        // Map IDs or short codes to validated short codes
        $validShortCodes = Skill::whereIn('id', $request->skills)
            ->orWhereIn('short_code', $request->skills)
            ->pluck('short_code')
            ->map(fn($code) => strtoupper($code))
            ->unique()
            ->toArray();

        // Get users with matching emails who are students
        $users = User::whereIn('email', $request->emails)->whereHas('student')->with('student')->get();
        $updatedCount = 0;

        foreach ($users as $user) {
            $student = $user->student;
            if ($student) {
                // Update assigned skills
                $student->update(['assigned_skills' => $validShortCodes]);

                // Re-evaluate default exam so their configs (want_reading, want_writing etc) match
                StudentExamConfig::where('student_id', $student->id)->delete();
                Student::assignDefaultExam($student);

                $updatedCount++;
            }
        }

        return response()->json([
            'message' => "Successfully updated skills for {$updatedCount} student(s).",
            'updated_count' => $updatedCount
        ]);
    }

    /**
     * Download Standard CSV Template
     */
    public function downloadTemplate()
    {
        $headers = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'gender',
            'birth_date',
            'address',
            'city',
            'country',
            'religion',
            'occupation',
            'student_code',
            'come_from',
            'student_type',
            'year_of_arabic',
            'not_adaptive',
            'package_id',
            'exam_category_id',
            'password',
            'want_listening',
            'want_reading',
            'want_grammar',
            'want_writing',
            'want_speaking'
        ];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            // Add a sample row
            fputcsv($file, [
                'John',
                'Doe',
                'john.doe@example.com',
                '123456789',
                'male',
                '2005-05-15',
                '123 Street',
                'Cairo',
                'Egypt',
                'None',
                'Student',
                'STU-101',
                'Direct',
                'Standard',
                '2024',
                '1',
                '1',
                '1',
                'pass123',
                '1',
                '1',
                '1',
                '0',
                '0' // Skills: L, R, G active; W, S inactive
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=student_import_template.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }

    /**
     * Download Excel template for bulk skills assignment
     */
    public function exportSkillsExcel()
    {
        return Excel::download(new StudentSkillsExport, 'students_skills_template.xlsx');
    }

    /**
     * Import Excel file for bulk skills assignment
     */
    public function importSkillsExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            DB::beginTransaction();
            Excel::import(new StudentSkillsImport, $request->file('file'));
            DB::commit();

            return response()->json(['message' => 'Bulk skills updated successfully from Excel file']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'An error occurred during import: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reset all exam attempts for a student to allow a clean retake.
     */
    public function resetExamAttempts(Request $request, Student $student)
    {
        try {
            DB::beginTransaction();

            // 1. Find all attempts
            $attempts = \App\Models\ExamAttempt::where('student_id', $student->id)->get();

            foreach ($attempts as $attempt) {
                // Cascading delete is preferred if relationships are properly set, 
                // but we'll do it explicitly here for safety with student answers.
                \App\Models\StudentAnswer::where('exam_attempt_id', $attempt->id)->delete();
                \App\Models\ExamAttemptSkill::where('exam_attempt_id', $attempt->id)->delete();
                $attempt->delete();
            }

            DB::commit();
            return response()->json(['message' => 'Candidate progress has been successfully reset. They can now retake the assessment.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to reset candidate progress: ' . $e->getMessage()], 500);
        }
    }
}
