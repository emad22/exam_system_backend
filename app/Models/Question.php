<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $appends = ['media_url'];

    protected $fillable = [
        'skill_id', 
        'group_tag', 
        'type', 
        'content', 
        'passage_content',
        'passage_group_id',
        'passage_randomize',
        'passage_limit',
        'media_path', 
        'difficulty_level', 
        'points',
        'min_words',
        'max_words'
    ];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function getMediaUrlAttribute()
    {
        if ($this->media_path) {
            return asset('storage/' . $this->media_path);
        }
        return null;
    }
}
