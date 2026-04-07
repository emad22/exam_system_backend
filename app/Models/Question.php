<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = ['skill_id', 'group_tag', 'type', 'content', 'media_path', 'difficulty_level', 'points'];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }
}
