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
        $allSkills = Skill::all();
        
        foreach ($rows as $row) {
            $email = $row['email'] ?? null;
            $code = $row['student_code'] ?? null;

            if (!$email && !$code) continue;

            $studentQuery = Student::query();
            if ($email) {
                $studentQuery->whereHas('user', fn($q) => $q->where('email', trim($email)));
            } elseif ($code) {
                $studentQuery->where('student_code', trim($code));
            }

            $student = $studentQuery->first();
            
            if ($student) {
                $finalShortCodes = [];

                // 1. Check for multi-column format (Skill Name columns)
                foreach ($allSkills as $skill) {
                    $slug = \Illuminate\Support\Str::slug($skill->name, '_');
                    if (isset($row[$slug]) && $this->isTrue($row[$slug])) {
                        $finalShortCodes[] = strtoupper($skill->short_code);
                    }
                }

                // 2. Fallback to legacy comma-separated format if no multi-column skills found
                if (empty($finalShortCodes)) {
                    $skillsStr = $row['skills'] ?? $row['assigned_skills'] ?? '';
                    if ($skillsStr) {
                        $parsed = explode(',', (string)$skillsStr);
                        foreach ($parsed as $c) {
                            $c = strtolower(trim($c));
                            $skillMatch = $allSkills->first(fn($s) => strtolower($s->short_code) === $c || strtolower($s->name) === $c);
                            if ($skillMatch) {
                                $finalShortCodes[] = strtoupper($skillMatch->short_code);
                            }
                        }
                    }
                }

                if (!empty($finalShortCodes)) {
                    $student->assigned_skills = array_unique($finalShortCodes);
                    $student->save();
                    // Re-evaluate their config to apply the new assignments
                    Student::assignDefaultExam($student);
                    // Sync package based on new skills
                    $student->syncPackageWithSkills();
                }
            }
        }
    }

    private function isTrue($value): bool
    {
        if (is_null($value)) return false;
        $val = strtolower(trim((string)$value));
        return in_array($val, ['1', 'true', 'yes', 'active', 'x']);
    }
}
