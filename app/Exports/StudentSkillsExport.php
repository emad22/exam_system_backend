<?php

namespace App\Exports;

use App\Models\Student;
use App\Models\Skill;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentSkillsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $skills;

    public function __construct()
    {
        $this->skills = Skill::all()->keyBy('id');
    }

    public function collection()
    {
        // Export existing students joining user data
        return Student::with('user')->get();
    }

    public function headings(): array
    {
        return [
            'Email',
            'Skills'
        ];
    }

    public function map($student): array
    {
        $assignedSkills = array_filter((array) $student->assigned_skills);
        $shortCodes = [];
        
        foreach ($assignedSkills as $val) {
            if (is_numeric($val)) {
                if (isset($this->skills[$val]) && $this->skills[$val]->short_code) {
                    $shortCodes[] = trim($this->skills[$val]->short_code);
                }
            } else {
                $shortCodes[] = trim($val);
            }
        }

        return [
            $student->user ? $student->user->email : 'N/A',
            implode(', ', array_unique($shortCodes))
        ];
    }
}
