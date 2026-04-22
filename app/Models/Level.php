<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Level extends Model
{
    protected $fillable = [
        'skill_id', 
        'name', 
        'level_number', 
        'instructions', 
        'instructions_audio', 
        'min_score', 
        'max_score', 
        'pass_threshold',
        'default_question_count',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected $appends = ['instructions_audio_url'];

    public function getInstructionsAudioUrlAttribute()
    {
        return $this->instructions_audio ? asset('storage/' . $this->instructions_audio) : null;
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
