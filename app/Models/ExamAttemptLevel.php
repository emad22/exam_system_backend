<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttemptLevel extends Model
{
    protected $fillable = [
        'exam_attempt_id',
        'skill_id',
        'level_number',
        'score',
        'status'
    ];

    public function attempt()
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}
