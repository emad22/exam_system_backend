<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = [
        'title', 
        'description', 
        'exam_type',
        'timer_type',
        'duration',
        'as_demo', 
        'play_in_real_player', 
        'passing_score',
        'is_default',
        'default_want_reading',
        'default_want_listening',
        'default_want_grammar',
        'default_want_writing',
        'default_want_speaking'
    ];

    protected $casts = [
        'as_demo' => 'boolean',
        'play_in_real_player' => 'boolean',
        'is_default' => 'boolean',
        'default_want_reading' => 'boolean',
        'default_want_listening' => 'boolean',
        'default_want_grammar' => 'boolean',
        'default_want_writing' => 'boolean',
        'default_want_speaking' => 'boolean',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function studentConfigs(): HasMany
    {
        return $this->hasMany(StudentExamConfig::class);
    }

    public function questionRules(): HasMany
    {
        return $this->hasMany(ExamQuestionRule::class);
    }

    public function skills(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'exam_skill')
            ->withPivot('duration', 'is_optional')
            ->withTimestamps();
    }
}
