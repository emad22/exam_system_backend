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
            
            'package_id' => 'nullable|exists:packages,id',
            'exam_type' => 'required|in:adult,children',
            'assigned_skills' => 'nullable|array',
            'password' => 'required|string|min:6',
        ]);

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
        $assignedSkills = $validated['assigned_skills'] ?? [];
        if (empty($assignedSkills) && !empty($validated['package_id'])) {
            $package = Package::find($validated['package_id']);
            $assignedSkills = $package ? ($package->skills ?? []) : [];
        }

        // 3. Create Profile (Student)
        $student = Student::create([
            'user_id' => $user->id,
            'student_code' => $validated['student_code'] ?? null,
            'come_from' => $validated['come_from'] ?? null,
            'student_type' => $validated['student_type'] ?? null,
            'year_of_arabic' => $validated['year_of_arabic'] ?? null,
            'not_adaptive' => $validated['not_adaptive'] ?? true,
            'package_id' => $validated['package_id'],
            'exam_type' => $validated['exam_type'],
            'assigned_skills' => $assignedSkills,
            'registration_source' => 'manual',
            'registration_date' => now(),
        ]);

        // 4. Automated Exam Enrollment & Skill Filtering
        Student::assignDefaultExam($student);

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
            'exam_type' => 'sometimes|required|in:adult,children',
            'assigned_skills' => 'sometimes|array',
            'password' => 'sometimes|nullable|string|min:6',
        ]);

        // 1. Update Profile (Student)
        $student->update($request->only([
            'package_id', 'exam_type', 'assigned_skills', 'student_type', 'student_code',
            'come_from', 'year_of_arabic', 'not_adaptive', 
        ]));

        // 2. Update Identity (User)
        if ($student->user_id) {
            $user = User::find($student->user_id);
            if ($user) {
                $userUpdate = $request->only([
                    'first_name', 'last_name', 'email', 'phone', 'gender', 
                    'address', 'city', 'country', 'religion', 'occupation',
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
        return response()->json($student->load(['user', 'package', 'attempts']));
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
            'ids'   => 'required|array',
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
        // $request->validate([
        //     'file' => 'required|mimes:xlsx,csv,xls',
        // ]);

        try {
            Excel::import(new StudentsImport($request->input('partner_id')), $request->file('file'));
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
            'skills' => 'required|array', // expected array of short codes e.g. ['r', 'w', 'l']
            'skills.*' => 'string'
        ]);

        // Map short codes to skill IDs
        $skillIds = Skill::whereIn('short_code', $request->skills)->pluck('id')->toArray();

        // Get users with matching emails who are students
        $users = User::whereIn('email', $request->emails)->whereHas('student')->with('student')->get();
        $updatedCount = 0;

        foreach ($users as $user) {
            $student = $user->student;
            if ($student) {
                // Update assigned skills
                $student->update(['assigned_skills' => $skillIds]);
                
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
            'first_name', 'last_name', 'email', 'phone', 'gender', 'birth_date', 
            'address', 'city', 'country', 'religion', 'occupation', 
            'student_code', 'come_from', 'student_type', 'year_of_arabic', 
            'not_adaptive', 'package_id', 'exam_type', 'password',
            'want_listening', 'want_reading', 'want_grammar', 'want_writing', 'want_speaking'
        ];

        $callback = function() use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            
            // Add a sample row
            fputcsv($file, [
                'John', 'Doe', 'john.doe@example.com', '123456789', 'male', '2005-05-15',
                '123 Street', 'Cairo', 'Egypt', 'None', 'Student',
                'STU-101', 'Direct', 'Standard', '2024',
                '1', '1', 'adult', 'pass123',
                '1', '1', '1', '0', '0' // Skills: L, R, G active; W, S inactive
            ]);
            
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=student_import_template.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
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
}
