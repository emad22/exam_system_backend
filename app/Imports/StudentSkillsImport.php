<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\Skill;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class StudentSkillsImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        // Pre-fetch all skills and group by lowercased short code
        $skills = Skill::all();
        $skillMap = [];
        foreach ($skills as $s) {
            if ($s->short_code) {
                $skillMap[strtolower(trim($s->short_code))] = $s->id;
            }
        }

        foreach ($rows as $row) {
            // Excel headers will be converted to snake_case by WithHeadingRow
            // headings: 'Student Name', 'Email', 'Student Code', 'Assigned Skills (Short Codes)'
            // output row keys: 'student_name', 'email', 'student_code', 'assigned_skills_short_codes'
            $email = $row['email'] ?? null;
            $code = $row['student_code'] ?? null;
            $skillsStr = $row['assigned_skills_short_codes'] ?? '';

            if (!$email && !$code) continue;

            $studentQuery = Student::query();
            if ($email) {
                $studentQuery->whereHas('user', fn($q) => $q->where('email', trim($email)));
            } elseif ($code) {
                $studentQuery->where('student_code', trim($code));
            }

            $student = $studentQuery->first();
            
            if ($student) {
                $parsedCodes = collect(explode(',', (string)$skillsStr))
                                ->map(fn($c) => strtolower(trim($c)))
                                ->filter()
                                ->toArray();
                
                $mappedIds = [];
                foreach($parsedCodes as $c) {
                    if (isset($skillMap[$c])) {
                        $mappedIds[] = (string)$skillMap[$c];
                    }
                }

                $student->assigned_skills = $mappedIds;
                $student->save();

                // Re-evaluate their config to apply the new assignments
                Student::assignDefaultExam($student);
            }
        }
    }
}
