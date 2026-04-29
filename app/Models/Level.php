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
        'default_standalone_quantity',
        'default_passage_quantity',
        'is_active',
        'is_random',
        'allows_retry'
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_random'    => 'boolean',
        'allows_retry' => 'boolean',
        'default_standalone_quantity' => 'integer',
        'default_passage_quantity' => 'integer'
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

    public function questions()
    {
        return $this->hasMany(Question::class, 'level_id');
    }
}
