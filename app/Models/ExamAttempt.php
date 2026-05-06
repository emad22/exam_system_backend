<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'student_id', 
        'exam_id', 
        'status', 
        'overall_score', 
        'current_position', 
        'proctor_value', 
        'ip_address',
        'started_at',
        'finished_at',
        'last_seen_question_id'
    ];

    protected $casts = [
        'current_position' => 'json',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function attemptSkills(): HasMany
    {
        return $this->hasMany(ExamAttemptSkill::class);
    }

    protected function getActivityDescription($action)
    {
        if ($action === 'created') {
            return "Student started exam: " . ($this->exam->title ?? 'Unknown Exam');
        }
        
        if ($action === 'updated' && $this->status === 'completed') {
            return "Student completed exam: " . ($this->exam->title ?? 'Unknown Exam');
        }

        $modelName = class_basename($this);
        return "{$action} {$modelName} #" . ($this->id ?? 'new');
    }

    public function attemptLevels(): HasMany
    {
        return $this->hasMany(ExamAttemptLevel::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }
}