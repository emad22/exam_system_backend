<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $fillable = [
        'exam_attempt_id', 
        'skill_id',
        'question_id', 
        'option_id', 
        'text_answer', 
        'media_answer', 
        'word_count',
        'is_correct', 
        'is_manual_graded',
        'points_awarded',
        'teacher_feedback',
        'grading_details'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'is_manual_graded' => 'boolean',
        'grading_details' => 'array',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class);
    }
}
