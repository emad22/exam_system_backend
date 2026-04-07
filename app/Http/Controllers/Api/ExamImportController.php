<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ExamImportController extends Controller
{
    /**
     * Import full exam from a folder structure.
     * Expected path: exam1/listening/level1.txt
     */
    public function importFolder(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file',
            'paths' => 'required|array',
        ]);

        $files = $request->file('files');
        $paths = $request->input('paths');

        if (count($files) !== count($paths)) {
            return response()->json(['message' => 'Files and paths count mismatch.'], 422);
        }

        $importedQuestionsTotal = 0;
        $examsCreated = [];

        DB::beginTransaction();

        try {
            // Group files by Exam Title (the root folder)
            $examGroups = [];

            foreach ($files as $index => $file) {
                if ($file->getClientOriginalExtension() !== 'txt' && $file->extension() !== 'txt') {
                    continue;
                }

                $path = $paths[$index];
                $parts = explode('/', str_replace('\\', '/', $path));

                if (count($parts) >= 3) {
                    $examTitle = $parts[0];
                    $skillName = $parts[count($parts) - 2];
                    $fileName = $parts[count($parts) - 1];

                    $levelNum = filter_var($fileName, FILTER_SANITIZE_NUMBER_INT);
                    $difficultyLevel = intval($levelNum) > 0 ? intval($levelNum) : 1;

                    if (!isset($examGroups[$examTitle])) {
                        $examGroups[$examTitle] = [];
                    }

                    if (!isset($examGroups[$examTitle][$skillName])) {
                        $examGroups[$examTitle][$skillName] = [];
                    }

                    $examGroups[$examTitle][$skillName][] = [
                        'file' => $file,
                        'level' => $difficultyLevel
                    ];
                }
            }

            if (empty($examGroups)) {
                return response()->json(['message' => 'No valid legacy exam folders found.'], 422);
            }

            foreach ($examGroups as $examTitle => $skillsArr) {
                // 1. Create or Find Exam
                // Note: The previous default language is mostly ID 1 based on DB seed, we will let it hit defaults or null
                $exam = Exam::firstOrCreate(
                    ['title' => $examTitle],
                    [
                        'description' => "Auto-imported legacy exam ($examTitle)",
                        'exam_type' => 'adult',
                        'passing_score' => 60,
                        'is_adaptive' => false
                    ]
                );
                
                $examsCreated[] = $exam->title;

                foreach ($skillsArr as $skillName => $levels) {
                    // 2. Create or Find Skill
                    $skill = Skill::firstOrCreate(['name' => $skillName]);

                    // Sync Exam Skill Configuration First!
                    DB::table('exam_skill')->updateOrInsert(
                        ['exam_id' => $exam->id, 'skill_id' => $skill->id],
                        ['duration' => 30, 'is_optional' => false]
                    );

                    foreach ($levels as $levelData) {
                        $file = $levelData['file'];
                        $difficultyLevel = $levelData['level'];

                        $content = file_get_contents($file->getRealPath());
                        $content = str_replace("\r\n", "\n", $content);
                        $lines = explode("\n", $content);

                        $questionCountInFile = 0;

                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;

                            $cols = explode("\t", $line);
                            if (count($cols) < 2) continue;

                            $questionText = trim($cols[0]);
                            if (empty($questionText)) continue;

                            $question = Question::create([
                                'skill_id' => $skill->id,
                                'difficulty_level' => $difficultyLevel,
                                'content' => $questionText,
                                'type' => 'mcq',
                                'points' => $difficultyLevel * 10,
                                'group_tag' => trim($examTitle),
                            ]);

                            // The first column after the question is the correct answer
                            for ($i = 1; $i < count($cols); $i++) {
                                $optionText = trim($cols[$i]);
                                if (empty($optionText)) continue;

                                QuestionOption::create([
                                    'question_id' => $question->id,
                                    'option_text' => $optionText,
                                    'is_correct' => ($i === 1),
                                ]);
                            }

                            $questionCountInFile++;
                            $importedQuestionsTotal++;
                        }

                        // Set up Exam Question Rules exactly to pull this newly imported file's quantity
                        if ($questionCountInFile > 0) {
                            DB::table('exam_question_rules')->insert([
                                'exam_id' => $exam->id,
                                'skill_id' => $skill->id,
                                'difficulty_level' => $difficultyLevel,
                                'quantity' => $questionCountInFile,
                                'group_tag' => trim($examTitle),
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            $examsList = implode(', ', $examsCreated);
            return response()->json([
                'message' => "Successfully created Exams: [$examsList] and imported $importedQuestionsTotal questions.",
                'imported_questions' => $importedQuestionsTotal,
                'exams_created' => count($examsCreated),
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
