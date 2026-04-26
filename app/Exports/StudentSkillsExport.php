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
        $headers = ['Email'];
        foreach ($this->skills as $skill) {
            $headers[] = $skill->name;
        }
        return $headers;
    }

    public function map($student): array
    {
        $row = [$student->user ? $student->user->email : 'N/A'];
        
        $assigned = array_map('strtoupper', array_filter((array) $student->assigned_skills));

        foreach ($this->skills as $skill) {
            $code = strtoupper($skill->short_code);
            // Check if student has this skill
            $row[] = in_array($code, $assigned) ? '1' : '0';
        }

        return $row;
    }
}
