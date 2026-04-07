<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Student;
use App\Models\Skill;
use App\Models\Level;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        return response()->json([
            'students_count' => Student::count(),
            'exams_count' => Exam::count(),
            'attempts_count' => ExamAttempt::count(),
            'recent_attempts' => ExamAttempt::with(['student', 'exam'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get(),
        ]);
    }

    /**
     * Get all students with their basic stats
     */
    public function students(Request $request)
    {
        $students = Student::with(['user', 'package'])->withCount('attempts')->paginate(30);
        return response()->json($students);
    }

    /**
     * Update student details (Phase 7 - Modal)
     */
    public function updateStudent(Request $request, Student $student)
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . ($student->user_id ?? 0),
            'package_id' => 'sometimes|nullable|exists:packages,id',
            'exam_type' => 'sometimes|required|in:adult,children',
            'language_level' => 'sometimes|nullable|string',
            'assigned_skills' => 'sometimes|array',
            'password' => 'sometimes|nullable|string|min:6', // New field
        ]);

        // Update Student Profile
        $student->update($validated);

        // Update Linked Identity (User)
        if ($student->user_id) {
            $user = \App\Models\User::find($student->user_id);
            if ($user) {
                $userUpdate = [];
                if (isset($validated['first_name']) || isset($validated['last_name'])) {
                    $userUpdate['name'] = ($validated['first_name'] ?? $student->first_name) . ' ' . ($validated['last_name'] ?? $student->last_name);
                }
                if (isset($validated['email'])) {
                    $userUpdate['email'] = $validated['email'];
                }
                if (!empty($validated['password'])) {
                    $userUpdate['password'] = \Illuminate\Support\Facades\Hash::make($validated['password']);
                }
                if (!empty($userUpdate)) {
                    $user->update($userUpdate);
                }
            }
        }

        return response()->json(['message' => 'Student and User profile updated successfully.', 'student' => $student->load('package')]);
    }

    /**
     * Batch import students from Excel (Phase 6)
     */
    public function batchImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120',
        ]);

        try {
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\StudentsImport, $request->file('file'));
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
     * Reset a specific exam attempt (Void it) so student can retake
     */
    public function resetAttempt(Request $request, ExamAttempt $attempt)
    {
        // Only Admin or Teacher can reset
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $attempt->update(['status' => 'voided']);
        
        return response()->json(['message' => 'Exam attempt voided successfully. Student can now retake the exam.']);
    }

    /**
     * Get reports (For Supervisor and Admin)
     */
    public function reports(Request $request)
    {
        $attempts = ExamAttempt::with(['student', 'exam'])->where('status', 'completed')->paginate(20);
        return response()->json($attempts);
    }

    /**
     * Get all skills
     */
    public function skills()
    {
        return response()->json(\App\Models\Skill::withCount('questions')->orderBy('name')->get());
    }

    /**
     * Get all languages for dropdown
     */
    public function languages()
    {
        return response()->json(\App\Models\Language::orderBy('name')->get());
    }

    /**
     * Get all packages
     */
    public function packages()
    {
        return response()->json(\App\Models\Package::orderBy('skills_count')->get());
    }

    /**
     * Get all exams with language info
     */
    public function exams()
    {
        return response()->json(Exam::with('language')->withCount('attempts')->latest()->get());
    }

    /**
     * Get all questions with skill info
     */
    public function questions()
    {
        return response()->json(\App\Models\Question::with('skill')->withCount('options')->latest()->paginate(30));
    }

    /**
     * Get a single question with its options
     */
    public function getQuestion(\App\Models\Question $question)
    {
        return response()->json($question->load('options', 'skill'));
    }

    /**
     * Update an existing question and its options
     */
    public function updateQuestion(Request $request, \App\Models\Question $question)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'skill_id'         => 'required|exists:skills,id',
            'type'             => 'required|in:mcq,true_false,short_answer,writing,speaking',
            'content'          => 'required|string',
            'difficulty_level' => 'required|integer|min:1|max:9',
            'points'           => 'required|integer|min:1',
            'group_tag'        => 'nullable|string|max:255',
            'options'          => 'nullable|array',
            'options.*.option_text' => 'required_with:options|string',
            'options.*.is_correct'  => 'required_with:options|boolean',
        ]);

        $question->update([
            'skill_id'         => $validated['skill_id'],
            'type'             => $validated['type'],
            'content'          => $validated['content'],
            'difficulty_level' => $validated['difficulty_level'],
            'points'           => $validated['points'],
            'group_tag'        => $validated['group_tag'] ?? $question->group_tag,
        ]);

        // Replace all options
        if (isset($validated['options'])) {
            $question->options()->delete();
            foreach ($validated['options'] as $opt) {
                $question->options()->create([
                    'option_text' => $opt['option_text'],
                    'is_correct'  => $opt['is_correct'],
                ]);
            }
        }

        return response()->json([
            'message'  => 'Question updated successfully.',
            'question' => $question->load('options')
        ]);
    }

    /**
     * Delete a question and its options
     */
    public function deleteQuestion(Request $request, \App\Models\Question $question)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }
        $question->options()->delete();
        $question->delete();
        return response()->json(['message' => 'Question deleted successfully.']);
    }

    /**
     * Store new student (Phase 5)
     */
    public function storeStudent(Request $request)
    {
        // Only Admin or Teacher can add students
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'package_id' => 'nullable|exists:packages,id',
            'exam_type' => 'required|in:adult,children',
            'assigned_skills' => 'nullable|array',
            'password' => 'required|string|min:6',
        ]);

        // 1. Create Identity (User)
        $user = \App\Models\User::create([
            'name' => $validated['first_name'] . ' ' . $validated['last_name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => 'student',
        ]);

        // 2. Fetch Package Skills if assigned_skills not provided
        $assignedSkills = $validated['assigned_skills'] ?? [];
        if (empty($assignedSkills) && !empty($validated['package_id'])) {
            $package = \App\Models\Package::find($validated['package_id']);
            $assignedSkills = $package ? ($package->skills ?? []) : [];
        }

        // 3. Create Profile (Student)
        $student = Student::create([
            'user_id' => $user->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'package_id' => $validated['package_id'],
            'exam_type' => $validated['exam_type'],
            'assigned_skills' => $assignedSkills,
            'registration_source' => 'manual',
        ]);

        return response()->json([
            'message' => 'Student and User account created successfully.',
            'student' => $student->load('package')
        ], 201);
    }

    /**
     * Level Management
     */
    public function getSkillsWithLevels()
    {
        return response()->json(Skill::with('levels')->get());
    }

    public function getSkillWithLevels(Skill $skill)
    {
        return response()->json($skill->load('levels'));
    }

    public function updateLevel(Request $request, Level $level)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'min_score' => 'sometimes|required|integer',
            'max_score' => 'sometimes|required|integer',
            'pass_threshold' => 'sometimes|required|integer|min:0|max:100',
        ]);

        $level->update($validated);

        return response()->json([
            'message' => 'Level updated successfully',
            'level' => $level
        ]);
    }

    /**
     * Store new Exam (Phase 5)
     */
    public function storeExam(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'language_id' => 'required|exists:languages,id',
            'exam_type' => 'required|in:adult,children',
            'passing_score' => 'required|numeric|min:0|max:100',
            'is_adaptive' => 'boolean',
            'skills' => 'required|array|min:1',
            'skills.*.skill_id' => 'required|exists:skills,id',
            'skills.*.duration' => 'required|integer|min:1',
            'skills.*.is_optional' => 'boolean',
            'skills.*.rules' => 'nullable|array',
        ]);

        // Automatically set the default_want_... boolean flags based on incoming skills
        $skillNames = \App\Models\Skill::whereIn('id', collect($validated['skills'])->pluck('skill_id'))->pluck('name')->map(fn($n) => strtolower($n))->toArray();
        
        $exam = Exam::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'language_id' => $validated['language_id'],
            'exam_type' => $validated['exam_type'],
            'passing_score' => $validated['passing_score'],
            'is_adaptive' => $validated['is_adaptive'] ?? false,
            'default_want_reading' => in_array('reading', $skillNames),
            'default_want_listening' => in_array('listening', $skillNames),
            'default_want_grammar' => in_array('grammar', $skillNames),
            'default_want_writing' => in_array('writing', $skillNames),
            'default_want_speaking' => in_array('speaking', $skillNames),
        ]);

        foreach ($validated['skills'] as $skill) {
            $exam->skills()->attach($skill['skill_id'], [
                'duration' => $skill['duration'],
                'is_optional' => $skill['is_optional'] ?? false
            ]);

            // Save question rules if provided
            if (isset($skill['rules']) && is_array($skill['rules'])) {
                foreach ($skill['rules'] as $rule) {
                    $exam->questionRules()->create([
                        'skill_id' => $skill['skill_id'],
                        'difficulty_level' => $rule['difficulty_level'] ?? null,
                        'group_tag' => $rule['group_tag'] ?? null,
                        'quantity' => $rule['quantity'] ?? 5, // Default to 5 if not set
                        'randomize' => $rule['randomize'] ?? true,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Exam created successfully with skills.',
            'exam' => $exam->load('skills')
        ], 201);
    }

    public function getExam(Exam $exam)
    {
        return response()->json($exam->load(['skills', 'questionRules', 'language']));
    }

    public function updateExam(Request $request, Exam $exam)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'language_id' => 'required|exists:languages,id',
            'exam_type' => 'required|in:adult,children',
            'passing_score' => 'required|numeric|min:0|max:100',
            'is_adaptive' => 'boolean',
            'skills' => 'required|array|min:1',
            'skills.*.skill_id' => 'required|exists:skills,id',
            'skills.*.duration' => 'required|integer|min:1',
            'skills.*.is_optional' => 'boolean',
            'skills.*.rules' => 'nullable|array',
        ]);

        $skillNames = \App\Models\Skill::whereIn('id', collect($validated['skills'])->pluck('skill_id'))->pluck('name')->map(fn($n) => strtolower($n))->toArray();

        $exam->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'language_id' => $validated['language_id'],
            'exam_type' => $validated['exam_type'],
            'passing_score' => $validated['passing_score'],
            'is_adaptive' => $validated['is_adaptive'] ?? false,
            'default_want_reading' => in_array('reading', $skillNames),
            'default_want_listening' => in_array('listening', $skillNames),
            'default_want_grammar' => in_array('grammar', $skillNames),
            'default_want_writing' => in_array('writing', $skillNames),
            'default_want_speaking' => in_array('speaking', $skillNames),
        ]);

        // Sync Skills
        $pivotSkills = [];
        foreach ($validated['skills'] as $skill) {
            $pivotSkills[$skill['skill_id']] = [
                'duration' => $skill['duration'],
                'is_optional' => $skill['is_optional'] ?? false
            ];
        }
        $exam->skills()->sync($pivotSkills);

        // Sync Rules (Delete old, create new)
        $exam->questionRules()->delete();
        foreach ($validated['skills'] as $skill) {
            if (isset($skill['rules']) && is_array($skill['rules'])) {
                foreach ($skill['rules'] as $rule) {
                    $exam->questionRules()->create([
                        'skill_id' => $skill['skill_id'],
                        'difficulty_level' => $rule['difficulty_level'] ?? null,
                        'group_tag' => $rule['group_tag'] ?? null,
                        'quantity' => $rule['quantity'] ?? 5,
                        'randomize' => $rule['randomize'] ?? true,
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Exam updated successfully.',
            'exam' => $exam->load('skills')
        ]);
    }

    /**
     * Store new Skill (Phase 5)
     */
    public function storeSkill(Request $request)
    {
        if (!in_array($request->user()->role, ['admin'])) {
            return response()->json(['error' => 'Only admins can add skills.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:skills',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:255'
        ]);

        $skill = \App\Models\Skill::create($validated);

        return response()->json([
            'message' => 'Skill created successfully.',
            'skill' => $skill
        ], 201);
    }

    /**
     * Delete existing Skill
     */
    public function deleteSkill(Request $request, \App\Models\Skill $skill)
    {
        if (!in_array($request->user()->role, ['admin'])) {
            return response()->json(['error' => 'Only admins can delete skills.'], 403);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Delete related questions and rules
            \App\Models\ExamQuestionRule::where('skill_id', $skill->id)->delete();
            
            // Delete questions (which will also cascade delete options if foreign keys are set up, but let's manual cascade options if needed)
            $questionIds = \App\Models\Question::where('skill_id', $skill->id)->pluck('id');
            \App\Models\QuestionOption::whereIn('question_id', $questionIds)->delete();
            \App\Models\Question::whereIn('id', $questionIds)->delete();

            // Clear from exams
            \Illuminate\Support\Facades\DB::table('exam_skill')->where('skill_id', $skill->id)->delete();
            
            // Clear levels
            \App\Models\Level::where('skill_id', $skill->id)->delete();

            $skill->delete();

            \Illuminate\Support\Facades\DB::commit();
            return response()->json(['message' => 'Skill and all related content deleted successfully.']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['error' => 'Failed to delete skill. Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store new Question with Options (Phase 7)
     */
    public function storeQuestion(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'teacher'])) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'skill_id' => 'required|exists:skills,id',
            'type' => 'required|in:mcq,true_false,short_answer,writing,speaking',
            'content' => 'required|string',
            'difficulty_level' => 'required|integer|min:1|max:9',
            'points' => 'required|integer|min:1',
            'options' => 'required_if:type,mcq,true_false,short_answer|array',
            'options.*.option_text' => 'required_with:options|string',
            'options.*.is_correct' => 'required_with:options|boolean',
        ]);

        $question = \App\Models\Question::create([
            'skill_id' => $validated['skill_id'],
            'group_tag' => $request->group_tag,
            'type' => $validated['type'],
            'content' => $validated['content'],
            'difficulty_level' => $validated['difficulty_level'],
            'points' => $validated['points'],
        ]);

        if (isset($validated['options']) && is_array($validated['options'])) {
            foreach ($validated['options'] as $option) {
                $question->options()->create($option);
            }
        }

        return response()->json([
            'message' => 'Question created successfully with options.',
            'question' => $question->load('options')
        ], 201);
    }
    /**
     * Get all staff members (Admin, Teacher, Supervisor)
     */
    public function getStaff(Request $request)
    {
        $staff = \App\Models\User::where('role', '!=', 'student')
            ->orderBy('role')
            ->paginate(50);
        return response()->json($staff);
    }

    /**
     * Provision a new staff identity
     */
    public function storeStaff(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Only high-level administrators can provision new staff.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,teacher,supervisor'
        ]);

        $staff = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Staff identity provisioned successfully.',
            'staff' => $staff
        ], 201);
    }

    /**
     * Update an existing staff role or identity
     */
    public function updateStaff(Request $request, \App\Models\User $user)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized role modification.'], 403);
        }

        if ($user->role === 'student') {
            return response()->json(['error' => 'Use student identity management for this account.'], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|required|in:admin,teacher,supervisor',
            'password' => 'sometimes|nullable|string|min:6'
        ]);

        if (isset($validated['name'])) $user->name = $validated['name'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['role'])) $user->role = $validated['role'];
        if (!empty($validated['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Staff identity updated successfully.',
            'staff' => $user
        ]);
    }

    /**
     * Revoke staff access (Delete)
     */
    public function deleteStaff(Request $request, \App\Models\User $user)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized access revocation.'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'Cannot revoke own access.'], 422);
        }

        if ($user->role === 'student') {
            return response()->json(['error' => 'Use identity management for students.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Staff access revoked successfully.']);
    }
}
