<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttemptSkill extends Model
{
    protected $table = 'exam_attempt_skills';

    protected $fillable = [
        'exam_attempt_id',
        'skill_id',
        'max_level_reached',
        'score',
        'status', // in_progress, completed, failed, skipped
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
