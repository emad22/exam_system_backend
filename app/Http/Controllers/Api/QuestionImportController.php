<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class QuestionImportController extends Controller
{
    /**
     * Import questions from a CSV file.
     * Expected format: skill_id, type, content, difficulty, points, option1|option2|option3, correct_index (0-based)
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), "r");
        
        // Skip header if exists
        $header = fgetcsv($handle);
        
        $importedCount = 0;
        
        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) < 5) continue;
                
                $question = Question::create([
                    'skill_id' => $row[0],
                    'type' => $row[1] ?? 'mcq',
                    'content' => $row[2],
                    'difficulty_level' => $row[3],
                    'points' => $row[4],
                ]);

                // Handle Options if MCQ
                if ($question->type === 'mcq' && isset($row[5])) {
                    $options = explode('|', $row[5]);
                    $correctIndex = (int)($row[6] ?? 0);
                    
                    foreach ($options as $index => $optText) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => trim($optText),
                            'is_correct' => ($index === $correctIndex),
                        ]);
                    }
                }
                $importedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to import: ' . $e->getMessage()], 500);
        } finally {
            fclose($handle);
        }

        return response()->json([
            'message' => 'Successfully imported ' . $importedCount . ' questions.',
        ]);
    }

    /**
     * Import full Exams from a folder structure.
     * Expected path: exam1/listening/level1.txt
     */
    public function importFolder(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*.examName' => 'required|string',
            'files.*.skillName' => 'required|string',
            'files.*.fileName' => 'required|string',
            'files.*.content' => 'present|string|nullable',
        ]);

        $fileDataArray = $request->input('files');

        DB::beginTransaction();

        try {
            // Group files by Exam -> Skill -> Level
            $structuredData = [];
            foreach ($fileDataArray as $fileData) {
                $examName = $fileData['examName'];
                $skillName = $fileData['skillName'];
                $fileName = $fileData['fileName'];
                $content = $fileData['content'];

                $structuredData[$examName][$skillName][$fileName] = $content;
            }

            $importedQuestions = 0;
            $examsCreatedCount = 0;
            $defaultLang = \App\Models\Language::first()->id ?? null;

            foreach ($structuredData as $examTitle => $skillsConfig) {
                // Create or find Exam (Remove missing columns)
                $exam = \App\Models\Exam::firstOrCreate(
                    ['title' => $examTitle],
                    [
                        'description' => "Legacy import: $examTitle",
                        'exam_category_id' => \App\Models\ExamCategory::where('is_active', true)->first()->id ?? null,
                        'passing_score' => 50,
                    ]
                );
                $examsCreatedCount++;

                foreach ($skillsConfig as $skillName => $levelFiles) {
                    $skill = \App\Models\Skill::firstOrCreate(['name' => $skillName]);

                    // Attach skill to exam if not exists
                    if (!$exam->skills->contains($skill->id)) {
                        $exam->skills()->attach($skill->id, [
                            'duration' => 30, // Default duration per skill
                            'is_optional' => false,
                        ]);
                    }

                    // Pre-parse the 'count' file if it exists
                    $quantitiesByLevel = [];
                    if (isset($levelFiles['count'])) {
                        $countLines = explode("\n", str_replace("\r\n", "\n", $levelFiles['count']));
                        foreach ($countLines as $idx => $line) {
                            $q = intval(trim($line));
                            if ($q > 0) $quantitiesByLevel[$idx + 1] = $q;
                        }
                        unset($levelFiles['count']); // Don't treat count as a question file
                    }

                    $importedCountInFile = 0;
                    foreach ($levelFiles as $fileName => $content) {
                        $levelNum = filter_var($fileName, FILTER_SANITIZE_NUMBER_INT);
                        $difficultyLevel = intval($levelNum) > 0 ? intval($levelNum) : 1;
                        $isInstructionFile = strpos($fileName, '_inst') !== false;

                        // Ensure the Level record exists
                        $levelModel = \App\Models\Level::firstOrCreate([
                            'skill_id' => $skill->id,
                            'level_number' => $difficultyLevel,
                        ], [
                            'name' => "Level $difficultyLevel",
                            'min_score' => 0,
                            'max_score' => 100,
                        ]);

                        if ($isInstructionFile) {
                            $levelModel->update(['instructions' => trim($content)]);
                            continue;
                        }

                        // Parse Questions with flexible delimiter (Tab or 4+ spaces)
                        $content = str_replace("\r\n", "\n", $content);
                        $lines = explode("\n", $content);
                        $questionsInFile = 0;

                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;

                            // Robust matching: Split by Tab or 3+ spaces
                            $cols = preg_split('/\t|\s{3,}/', $line);
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

                            for ($i = 1; $i < count($cols); $i++) {
                                $optionText = trim($cols[$i]);
                                if (empty($optionText)) continue;

                                QuestionOption::create([
                                    'question_id' => $question->id,
                                    'option_text' => $optionText,
                                    'is_correct' => ($i === 1),
                                ]);
                            }

                            $importedQuestions++;
                            $questionsInFile++;
                        }

                        // Create Exam Rules
                        $ruleQuantity = $quantitiesByLevel[$difficultyLevel] ?? $questionsInFile;
                        if ($ruleQuantity > 0) {
                            \App\Models\ExamQuestionRule::updateOrCreate(
                                [
                                    'exam_id' => $exam->id,
                                    'skill_id' => $skill->id,
                                    'difficulty_level' => $difficultyLevel,
                                    'group_tag' => trim($examTitle),
                                ],
                                ['quantity' => $ruleQuantity]
                            );
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Import Successful: $importedQuestions questions added to $examsCreatedCount exams.",
                'details' => [
                    'exams' => $examsCreatedCount,
                    'questions' => $importedQuestions
                ]
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
