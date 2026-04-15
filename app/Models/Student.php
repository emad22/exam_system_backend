<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    // Credentials move to User table. This table stores the Profile.
    
    protected $fillable = [
        'user_id',
        'student_code',
        'come_from',
        'registration_date',
        'from_promotion',
        'student_type',
        'parent_code',
        'year_of_arabic',
        'not_adaptive',
        'num_of_login',
        'package_id',
        'exam_type',
        'assigned_skills',
        'registration_source',
        'wordpress_user_id',
        'partner_id', 
    ];

    protected $casts = [
        'not_adaptive' => 'boolean',
        'birth_date' => 'date',
        'assigned_skills' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($student) {
            if (empty($student->parent_code)) {
                $student->parent_code = 'PRNT-' . strtoupper(substr(uniqid(), -6));
            }
            if (empty($student->student_code)) {
                $student->student_code = 'STU-' . strtoupper(substr(uniqid(), -6));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function configs(): HasMany
    {
        return $this->hasMany(StudentExamConfig::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Automatically assign the latest matching exam based on category (Adult/Children)
     * and filter skills to only those assigned to the student.
     */
    public static function assignDefaultExam(Student $student)
    {
        // 1. Find the designated default exam, or fallback to the latest matching the type
        $exam = null;

        // Try to get exam from Package first
        $student->loadMissing('package');
        if ($student->package && $student->package->exam_id) {
            $exam = Exam::find($student->package->exam_id);
        }

        // Fallback to default exam matching type
        if (!$exam) {
            $exam = Exam::where('exam_type', $student->exam_type)
                        ->where('is_default', true)
                        ->latest()
                        ->first();
        }

        // Fallback to latest exam matching type
        if (!$exam) {
            $exam = Exam::where('exam_type', $student->exam_type)
                        ->latest()
                        ->first();
        }

        if (!$exam) return null;

        // 2. Determine Skill Selection Priority Loop
        // Priority 1: Direct Student skills
        $assignedSkillIds = array_filter((array) $student->assigned_skills);
        
        // Priority 2: Package skills
        if (empty($assignedSkillIds) && $student->package && $student->package->skills) {
            $assignedSkillIds = array_filter((array) $student->package->skills);
        }
        
        // Priority 3: Inherit directly from Exam Defaults logic
        if (empty($assignedSkillIds)) {
            return StudentExamConfig::create([
                'student_id'      => $student->id,
                'exam_id'         => $exam->id,
                'want_listening'  => (bool) $exam->default_want_listening,
                'want_reading'    => (bool) $exam->default_want_reading,
                'want_grammar'    => (bool) $exam->default_want_grammar,
                'want_writing'    => (bool) $exam->default_want_writing,
                'want_speaking'   => (bool) $exam->default_want_speaking,
            ]);
        }

        // Otherwise, fetch skills by ID or short_code
        $numericIds = array_filter($assignedSkillIds, 'is_numeric');
        $stringCodes = array_filter($assignedSkillIds, fn($val) => !is_numeric($val));

        $skillsQuery = Skill::query();
        if (!empty($numericIds)) {
            $skillsQuery->orWhereIn('id', $numericIds);
        }
        if (!empty($stringCodes)) {
            $skillsQuery->orWhereIn('short_code', $stringCodes);
        }
        $skills = $skillsQuery->get();

        $skillNames = $skills->pluck('name')->map(fn($v) => strtolower(trim($v)))->toArray();
        $skillCodes = $skills->pluck('short_code')->filter()->map(fn($v) => strtolower(trim($v)))->toArray();

        // Helper to check
        $hasSkill = function($aliases) use ($skillNames, $skillCodes) {
            foreach((array)$aliases as $alias) {
                $alias = strtolower($alias);
                if (in_array($alias, $skillNames) || in_array($alias, $skillCodes)) {
                    return true;
                }
            }
            return false;
        };

        return StudentExamConfig::create([
            'student_id'      => $student->id,
            'exam_id'         => $exam->id,
            'want_listening'  => $hasSkill(['listening', 'l']),
            'want_reading'    => $hasSkill(['reading', 'reading comprehension', 'r']),
            'want_grammar'    => $hasSkill(['grammar', 'structure', 'g']),
            'want_writing'    => $hasSkill(['writing', 'w']),
            'want_speaking'   => $hasSkill(['speaking', 's']),
        ]);
    }
}
