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
        // 1. Find the latest exam matching the student's exam_type
        $exam = Exam::where('exam_type', $student->exam_type)
                    ->latest()
                    ->first();

        if (!$exam) return null;

        // 2. Map assigned_skills IDs to Skill names
        $assignedSkillIds = (array) $student->assigned_skills;
        $skillNames = Skill::whereIn('id', $assignedSkillIds)
                          ->pluck('name')
                          ->map(fn($v) => strtolower($v))
                          ->toArray();

        // 3. Create the configuration
        return StudentExamConfig::create([
            'student_id'      => $student->id,
            'exam_id'         => $exam->id,
            'want_listening'  => in_array('listening', $skillNames),
            'want_reading'    => in_array('reading comprehension', $skillNames) || in_array('reading', $skillNames),
            'want_grammar'    => in_array('structure', $skillNames) || in_array('grammar', $skillNames),
            'want_writing'    => in_array('writing', $skillNames),
            'want_speaking'   => in_array('speaking', $skillNames),
        ]);
    }
}
