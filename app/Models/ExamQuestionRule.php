<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestionRule extends Model
{
    protected $fillable = [
        'exam_id',
        'skill_id',
        'difficulty_level', // New field
        'group_tag',
        'quantity',
        'randomize'
    ];

    protected $casts = [
        'randomize' => 'boolean',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
