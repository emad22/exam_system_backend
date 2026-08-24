<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptLevel;
use App\Models\ExamAttemptSkill;
use App\Models\ExamCategory;
use App\Models\Package;
use App\Models\Skill;
use App\Models\Student;
use App\Models\StudentAnswer;
use App\Models\StudentExamConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentService
{
    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Create a new student with their associated user account.
     */
    public function createStudent(array $data): Student
    {
        $assignedSkills   = $this->resolveSkillCodes($data['assigned_skills'] ?? []);
        $examCategoryId   = $this->resolveExamCategory(
            $data['package_id'] ?? null,
            $data['exam_category_id'] ?? null
        );

        $user = User::create([
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'username'    => $data['username'],
            'email'       => $data['email'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'gender'      => $data['gender'] ?? null,
            'birth_date'  => !empty($data['birth_date']) ? Carbon::parse($data['birth_date'])->toDateString() : null,
            'address'     => $data['address'] ?? null,
            'city'        => $data['city'] ?? null,
            'country'     => $data['country'] ?? null,
            'religion'    => $data['religion'] ?? null,
            'occupation'  => $data['occupation'] ?? null,
            'password'    => Hash::make($data['password']),
            'role'        => 'student',
        ]);

        // Fall back to package skills when assigned_skills not explicitly provided
        if (empty($assignedSkills) && !empty($data['package_id'])) {
            $package        = Package::find($data['package_id']);
            $assignedSkills = $package ? ($package->skills ?? []) : [];
        }

        $student = Student::create([
            'user_id'                       => $user->id,
            'student_code'                  => $data['student_code'] ?? null,
            'institution_code'              => $data['institution_code'] ?? null,
            'come_from'                     => $data['come_from'] ?? null,
            'student_type'                  => $data['student_type'] ?? null,
            'year_of_arabic'                => $data['year_of_arabic'] ?? null,
            'is_continue'                   => $data['is_continue'] ?? false,
            'allows_retry'                  => $data['allows_retry'] ?? false,
            'is_demo'                       => $data['is_demo'] ?? false,
            'is_demo_proctored'             => $data['is_demo_proctored'] ?? false,
            'bypass_identity_verification'  => $data['bypass_identity_verification'] ?? false,
            'package_id'                    => $data['package_id'] ?? null,
            'exam_category_id'              => $examCategoryId,
            'assigned_skills'               => $assignedSkills,
            'partner_id'                    => $data['partner_id'] ?? null,
            'registration_source'           => 'manual',
            'registration_date'             => now(),
        ]);

        Student::assignDefaultExam($student, $data['exam_id'] ?? null);

        return $student->load(['user', 'package', 'configs.exam']);
    }

    /**
     * Update an existing student and their user account.
     */
    public function updateStudent(Student $student, array $validated, array $requestKeys): Student
    {
        // Resolve skills if provided
        if (isset($validated['assigned_skills'])) {
            $validated['assigned_skills'] = $this->resolveSkillCodes($validated['assigned_skills']);
        }

        // Build student attribute update payload from explicitly sent fields
        $studentUpdate = array_intersect_key($validated, array_flip([
            'package_id', 'exam_category_id', 'student_type', 'student_code',
            'institution_code', 'come_from', 'year_of_arabic', 'is_continue',
            'allows_retry', 'is_demo', 'is_demo_proctored', 'bypass_identity_verification',
            'partner_id',
        ]));

        if (isset($validated['assigned_skills'])) {
            $studentUpdate['assigned_skills'] = $validated['assigned_skills'];
        }

        $student->update($studentUpdate);

        // Recalculate default exam/configs after updating package or assigned skills
        Student::assignDefaultExam($student);

        // Re-sync active exam attempts when skills or package changed
        if (isset($validated['assigned_skills']) || isset($validated['package_id'])) {
            $this->resyncAttempts($student);
        }

        // Update the linked User record
        if ($student->user_id) {
            $this->updateUserIdentity($student, $validated, $requestKeys);
        }

        return $student->load(['user', 'package']);
    }

    /**
     * Hard-delete a student and their user account.
     */
    public function deleteStudent(Student $student): void
    {
        $userId = $student->user_id;
        $student->delete();

        if ($userId) {
            User::destroy($userId);
        }
    }

    /**
     * Hard-delete multiple students and their user accounts.
     */
    public function bulkDeleteStudents(array $ids): void
    {
        $students = Student::whereIn('id', $ids)->get();
        $userIds  = [];

        foreach ($students as $student) {
            if ($student->user_id) {
                $userIds[] = $student->user_id;
            }
            $student->delete();
        }

        if (!empty($userIds)) {
            User::destroy($userIds);
        }
    }

    /**
     * Reset all exam attempts for a student so they can retake the assessment.
     */
    public function resetExamAttempts(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $attempts = ExamAttempt::where('student_id', $student->id)->get();

            foreach ($attempts as $attempt) {
                StudentAnswer::where('exam_attempt_id', $attempt->id)->delete();
                ExamAttemptLevel::where('exam_attempt_id', $attempt->id)->delete();
                ExamAttemptSkill::where('exam_attempt_id', $attempt->id)->delete();
                $attempt->delete();
            }
        });
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Normalise an array of skill IDs / short codes to uppercase short codes.
     */
    public function resolveSkillCodes(array $skills): array
    {
        if (empty($skills)) {
            return [];
        }

        return Skill::whereIn('id', $skills)
            ->orWhereIn('short_code', $skills)
            ->pluck('short_code')
            ->map(fn($code) => strtoupper($code))
            ->unique()
            ->toArray();
    }

    /**
     * Determine the exam_category_id to use for a new student.
     * Falls back to: package's exam category → first active category.
     */
    private function resolveExamCategory(?int $packageId, ?int $categoryId): ?int
    {
        if ($categoryId) {
            return $categoryId;
        }

        if ($packageId) {
            $package = Package::with(['exam'])->find($packageId);
            if ($package && $package->exam) {
                return $package->exam->exam_category_id;
            }
        }

        return ExamCategory::where('is_active', true)->first()?->id;
    }

    /**
     * Re-sync all active exam attempts after a student's skills / package changes.
     * Reopens attempts where new skills are uncompleted and recalculates positions.
     */
    private function resyncAttempts(Student $student): void
    {
        $attempts    = ExamAttempt::where('student_id', $student->id)->get();
        $examService = app(ExamService::class);

        foreach ($attempts as $attempt) {
            $exam = $attempt->exam;
            if (!$exam) {
                continue;
            }

            $allowedIdentifiers = $examService->getAllowedSkills($student);
            $assignedSkills     = [];

            foreach ($exam->skills as $skill) {
                if ($examService->skillMatchesIdentifiers($skill, $allowedIdentifiers)) {
                    $assignedSkills[] = $skill;
                }
            }

            // Sort skills by the canonical order (L → R → G → W → S)
            usort($assignedSkills, fn($a, $b) => $this->skillOrder($a->name) - $this->skillOrder($b->name));

            $assignedSkillIds = array_map(fn($s) => $s->id, $assignedSkills);

            $completedSkillIds = ExamAttemptSkill::where('exam_attempt_id', $attempt->id)
                ->whereIn('status', ['completed', 'failed', 'skipped'])
                ->pluck('skill_id')
                ->toArray();

            $hasUncompleted = collect($assignedSkillIds)
                ->contains(fn($sId) => !in_array($sId, $completedSkillIds));

            $status = $hasUncompleted ? 'ongoing' : $attempt->status;

            $pos = $attempt->current_position ?? [];
            $pos['skill_ids'] = $assignedSkillIds;

            if (empty($pos['skill_ids'])) {
                $pos['current_skill_index'] = 0;
            } elseif (!isset($pos['current_skill_index']) || $pos['current_skill_index'] >= count($pos['skill_ids'])) {
                $foundIndex = 0;
                foreach ($pos['skill_ids'] as $idx => $sId) {
                    if (!in_array($sId, $completedSkillIds)) {
                        $foundIndex = $idx;
                        break;
                    }
                }
                $pos['current_skill_index'] = $foundIndex;
            }

            $attempt->update(['status' => $status, 'current_position' => $pos]);
        }
    }

    /**
     * Update the User record linked to a student.
     */
    private function updateUserIdentity(Student $student, array $validated, array $requestKeys): void
    {
        $user = User::find($student->user_id);
        if (!$user) {
            return;
        }

        $allowedUserFields = [
            'first_name', 'last_name', 'email', 'phone', 'gender',
            'address', 'city', 'country', 'religion', 'occupation', 'username', 'is_active',
        ];

        $userUpdate = array_intersect_key($validated, array_flip(
            array_intersect($allowedUserFields, $requestKeys)
        ));

        if (!empty($validated['birth_date'])) {
            $userUpdate['birth_date'] = Carbon::parse($validated['birth_date'])->toDateString();
        }

        if (!empty($validated['password'])) {
            $userUpdate['password'] = Hash::make($validated['password']);
        }

        if (!empty($userUpdate)) {
            $user->update($userUpdate);
        }
    }

    /**
     * Return a canonical sort order for a skill by name.
     */
    private function skillOrder(string $name): int
    {
        $name = strtolower($name);
        if (str_contains($name, 'listening'))                                                         return 1;
        if (str_contains($name, 'reading'))                                                           return 2;
        if (str_contains($name, 'structure') || str_contains($name, 'grammar') || str_contains($name, 'gram')) return 3;
        if (str_contains($name, 'writing') || str_contains($name, 'writting') || str_contains($name, 'writ'))  return 4;
        if (str_contains($name, 'speaking') || str_contains($name, 'speak'))                         return 5;
        return 99;
    }
}
