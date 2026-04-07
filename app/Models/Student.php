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
        'first_name',
        'last_name',
        'a_name',
        'birth_date',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'gender',
        'religion',
        'occupation',
        'universty',
        'univ_year',
        'academic_year',
        'student_unique_id',
        'come_from',
        'language_level',
        'course_currently_in',
        'year_of_arabic',
        'not_adaptive',
        'num_of_login',
        'parent_code',
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
            if (empty($student->student_unique_id)) {
                $student->student_unique_id = 'STU-' . strtoupper(substr(uniqid(), -6));
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
}
