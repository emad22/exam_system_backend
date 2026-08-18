<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Student\BatchImportStudentRequest;
use App\Http\Requests\Admin\Student\BulkUpdateStudentSkillsRequest;
use App\Http\Requests\Admin\Student\BulkDestroyStudentRequest;
use App\Http\Requests\Admin\Student\StoreStudentRequest;
use App\Http\Requests\Admin\Student\UpdateStudentRequest;
use App\Http\Requests\Admin\Student\ImportSkillsExcelRequest;
use App\Models\ExamAttempt;
use App\Models\Level;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Student;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\StudentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\StudentsImport;
use App\Exports\StudentSkillsExport;
use App\Imports\StudentSkillsImport;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $studentService) {}

    /**
     * Get all students with their basic stats.
     */
    public function index(Request $request)
    {
        $query = Student::with(['user', 'package', 'attempts'])
            ->withCount('attempts')
            ->orderBy('id', 'desc');

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                  ->orWhere('student_code', 'like', "%{$search}%")
                  ->orWhere('institution_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('id', $search)
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('from_date')) {
            $fromDate = $request->from_date;
            $query->where(function ($q) use ($fromDate) {
                $q->whereDate('registration_date', '>=', $fromDate)
                  ->orWhere(function ($sub) use ($fromDate) {
                      $sub->whereNull('registration_date')
                          ->whereDate('created_at', '>=', $fromDate);
                  });
            });
        }

        if ($request->filled('to_date')) {
            $toDate = $request->to_date;
            $query->where(function ($q) use ($toDate) {
                $q->whereDate('registration_date', '<=', $toDate)
                  ->orWhere(function ($sub) use ($toDate) {
                      $sub->whereNull('registration_date')
                          ->whereDate('created_at', '<=', $toDate);
                  });
            });
        }

        $perPage  = (int) $request->input('per_page', 500);
        $students = $query->paginate($perPage);

        return response()->json($students);
    }

    /**
     * Store a new student.
     */
    public function store(StoreStudentRequest $request)
    {
        $student = $this->studentService->createStudent($request->validated());

        return response()->json([
            'message' => 'Student account created and Exam assigned successfully.',
            'student' => $student,
        ], 201);
    }

    /**
     * Display a specific student with full attempt details.
     */
    public function show(Student $student)
    {
        $student->load([
            'user',
            'package',
            'category',
            'attempts.exam',
            'attempts.certificate',
            'attempts.attemptSkills.skill',
            'attempts.lastSeenQuestion.skill',
            'attempts.lastSeenQuestion.options',
            'attempts.attemptLevels' => fn($q) => $q->orderBy('created_at', 'asc'),
        ]);

        $skillLookup = Skill::all()->keyBy('id');
        $allLevels   = Level::all();
        $levelMap    = $allLevels->where('skill_id', 1)->pluck('name', 'level_number')->toArray();
        $levelLookup = $allLevels->keyBy('id');

        $attemptIds = $student->attempts->pluck('id');
        $allAnswers = StudentAnswer::whereIn('exam_attempt_id', $attemptIds)
            ->with(['question.options'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('exam_attempt_id');

        $student->attempts->each(function ($attempt) use ($levelMap, $levelLookup, $skillLookup, $allAnswers) {
            $total   = $attempt->attemptSkills->sum('score');
            $count   = $attempt->attemptSkills->count();
            $attempt->total_score    = $total;
            $attempt->max_possible   = $count * 900;
            $attempt->score_display  = $count > 0 ? "$total / " . ($count * 900) : "0 / 0";

            $attemptAnswers = $allAnswers->get($attempt->id, collect());

            $attempt->attemptSkills->each(function ($as) use ($levelMap, $levelLookup, $attemptAnswers) {
                $displayLevel  = $as->status === 'completed'
                    ? $as->max_level_reached
                    : max($as->max_level_reached - 1, 1);
                $as->level_name = $levelMap[$displayLevel] ?? "Level {$displayLevel}";

                $lastAns = $attemptAnswers->first(fn($ans) => ($ans->question?->skill_id ?? null) == $as->skill_id);

                if ($lastAns && $as->status !== 'completed') {
                    $q = $lastAns->question;
                    if (!$q) return;

                    $correctOpt      = $q->options->where('is_correct', true)->first();
                    $displayContent  = strip_tags($q->content ?? '');
                    if (empty($displayContent)) {
                        $displayContent = $q->instructions ?? 'Audio/Media Question';
                    }

                    $levelRecord = $levelLookup->get($q->level_id);
                    $matchedOpt  = $lastAns->option_id ? $q->options->firstWhere('id', $lastAns->option_id) : null;

                    $as->termination_point = [
                        'question_id'    => $q->id,
                        'level_number'   => $levelRecord ? $levelRecord->level_number : '?',
                        'question_text'  => $displayContent,
                        'correct_answer' => $correctOpt ? strip_tags($correctOpt->option_text ?? '') : 'N/A',
                        'student_answer' => $matchedOpt
                            ? strip_tags($matchedOpt->option_text ?? 'N/A')
                            : ($lastAns->text_answer ?? 'N/A'),
                    ];
                }
            });

            if ($attempt->status === 'ongoing') {
                $attempt->outcome_text = 'In Progress';
            } else {
                $attempt->outcome_text = $attempt->placement_level
                    ? ($levelMap[$attempt->placement_level] ?? "Level {$attempt->placement_level}")
                    : 'Completed';
            }

            $lastSeenQ = $attempt->lastSeenQuestion;
            if ($lastSeenQ) {
                $correctOption    = $lastSeenQ->options->where('is_correct', true)->first();
                $studentAnsRecord = $attemptAnswers->firstWhere('question_id', $lastSeenQ->id);

                $studentChoice = 'No Answer Provided';
                if ($studentAnsRecord) {
                    if ($studentAnsRecord->option_id) {
                        $opt           = $lastSeenQ->options->firstWhere('id', $studentAnsRecord->option_id);
                        $studentChoice = $opt ? strip_tags($opt->option_text) : 'Unknown Option';
                    } elseif ($studentAnsRecord->text_answer) {
                        $studentChoice = $studentAnsRecord->text_answer;
                    }
                }

                $displayContent = strip_tags($lastSeenQ->content ?? '');
                if (empty($displayContent)) {
                    $displayContent = $lastSeenQ->instructions ?? 'Audio/Media Question';
                }

                $qLevel = $levelLookup->get($lastSeenQ->level_id);
                $attempt->last_activity = [
                    'skill_name'     => $lastSeenQ->skill?->name ?? 'Unknown',
                    'level_number'   => $qLevel ? $qLevel->level_number : '?',
                    'level_name'     => $qLevel ? ($levelMap[$qLevel->level_number] ?? 'Unknown') : 'Unknown',
                    'question_text'  => $displayContent,
                    'correct_answer' => $correctOption ? strip_tags($correctOption->option_text ?? '') : 'N/A',
                    'student_answer' => $studentChoice,
                    'question_id'    => $lastSeenQ->id,
                    'time'           => $attempt->updated_at->diffForHumans(),
                ];
            } else {
                $pos = $attempt->current_position;
                if ($pos && isset($pos['skill_ids'][$pos['current_skill_index']])) {
                    $skillId = $pos['skill_ids'][$pos['current_skill_index']];
                    $skill   = $skillLookup->get($skillId);
                    $attempt->last_activity = [
                        'skill_name'    => $skill ? $skill->name : 'Unknown',
                        'level_number'  => $pos['current_level'] ?? 1,
                        'level_name'    => $levelMap[$pos['current_level'] ?? 1] ?? 'Level 1',
                        'question_text' => 'Session Initialized',
                        'question_id'   => null,
                    ];
                }
            }

            $attempt->recent_answers = $attemptAnswers->take(5)->map(fn($ans) => [
                'question_text' => strip_tags($ans->question?->content ?? $ans->question?->instructions ?? 'Question'),
                'is_correct'    => (bool) $ans->is_correct,
                'time'          => $ans->created_at?->format('H:i:s') ?? '',
            ]);
        });

        return response()->json($student);
    }

    /**
     * Update student details.
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        $updated = $this->studentService->updateStudent(
            $student,
            $request->validated(),
            array_keys($request->all())
        );

        return response()->json([
            'message' => 'Student and User profile updated successfully.',
            'student' => $updated,
        ]);
    }

    /**
     * Remove a student (and their user identity).
     */
    public function destroy(Student $student)
    {
        $this->studentService->deleteStudent($student);

        return response()->json(['message' => 'Student record deleted successfully.']);
    }

    /**
     * Remove multiple students.
     */
    public function bulkDestroy(BulkDestroyStudentRequest $request)
    {
        $this->studentService->bulkDeleteStudents($request->validated('ids'));

        return response()->json(['message' => 'Selected student records deleted successfully.']);
    }

    /**
     * Batch import students from Excel.
     */
    public function batchImport(BatchImportStudentRequest $request)
    {
        $validated = $request->validated();

        try {
            $assignedSkills = $validated['assigned_skills'] ?? null;
            if (is_string($assignedSkills)) {
                $assignedSkills = json_decode($assignedSkills, true);
            }

            Excel::import(
                new StudentsImport(
                    $validated['partner_id'] ?? null,
                    $validated['package_id'] ?? null,
                    $assignedSkills
                ),
                $request->file('file')
            );

            return response()->json(['message' => 'Students imported successfully.']);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = collect($e->failures())->map(
                fn($f) => "Row {$f->row()}: " . implode(', ', $f->errors())
            )->toArray();

            return response()->json(['message' => 'Import failed.', 'errors' => $errors], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Batch update assigned skills for multiple students by email/username.
     */
    public function bulkUpdateSkills(BulkUpdateStudentSkillsRequest $request)
    {
        $validated = $request->validated();

        $validShortCodes = $this->studentService->resolveSkillCodes($validated['skills']);

        $users        = User::where(function ($q) use ($request) {
            $q->whereIn('email', $request->emails)
              ->orWhereIn('username', $request->emails);
        })->whereHas('student')->with('student')->get();

        $updatedCount = 0;

        foreach ($users as $user) {
            $student = $user->student;
            if (!$student) continue;

            $student->update(['assigned_skills' => $validShortCodes]);

            // Re-evaluate default exam configs
            \App\Models\StudentExamConfig::where('student_id', $student->id)->delete();
            Student::assignDefaultExam($student);
            $student->syncPackageWithSkills();

            $updatedCount++;
        }

        return response()->json([
            'message'       => "Successfully updated skills for {$updatedCount} student(s).",
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Download standard CSV template for student import.
     */
    public function downloadTemplate()
    {
        $headers = [
            'first_name', 'last_name', 'username', 'email', 'phone', 'gender',
            'birth_date', 'address', 'city', 'country', 'religion', 'occupation',
            'student_code', 'institution_code', 'come_from', 'student_type',
            'year_of_arabic', 'is_continue', 'package_id', 'exam_category_id',
            'password', 'want_listening', 'want_reading', 'want_grammar',
            'want_writing', 'want_speaking',
        ];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fputcsv($file, [
                'John', 'Doe', 'johndoe123', 'john.doe@example.com', '123456789',
                'male', '2005-05-15', '123 Street', 'Cairo', 'Egypt', 'None', 'Student',
                'STU-101', 'INST-456', 'Direct', 'Standard', '2024', '0', '1', '1',
                'pass123', '1', '1', '1', '0', '0',
            ]);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=student_import_template.csv',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ]);
    }

    /**
     * Download Excel template for bulk skills assignment.
     */
    public function exportSkillsExcel()
    {
        return Excel::download(new StudentSkillsExport, 'students_skills_template.xlsx');
    }

    /**
     * Import Excel file for bulk skills assignment.
     */
    public function importSkillsExcel(ImportSkillsExcelRequest $request)
    {
        $validated = $request->validated();

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
            $this->studentService->resetExamAttempts($student);

            return response()->json(['message' => 'Candidate progress has been successfully reset. They can now retake the assessment.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to reset candidate progress: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle the bypass_identity_verification flag for a student.
     */
    public function toggleBypassIdentityVerification(Request $request, Student $student)
    {
        try {
            $student->update([
                'bypass_identity_verification' => !$student->bypass_identity_verification,
            ]);

            return response()->json([
                'message'                       => 'Candidate identity verification bypass status updated successfully.',
                'bypass_identity_verification'  => $student->bypass_identity_verification,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update candidate bypass status: ' . $e->getMessage()], 500);
        }
    }
}
