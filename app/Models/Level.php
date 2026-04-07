<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Level extends Model
{
    protected $fillable = ['skill_id', 'name', 'level_number', 'min_score', 'max_score', 'pass_threshold'];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
