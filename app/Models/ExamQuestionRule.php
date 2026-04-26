<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestionRule extends Model
{
    protected $fillable = [
        'exam_id',
        'skill_id',
        'level_id',
        'group_tag', // Keep for backward compatibility if needed, but we focus on level_id
        'quantity',
        'standalone_quantity',
        'passage_quantity',
        'randomize'
    ];

    protected $casts = [
        'randomize' => 'boolean',
        'level_id' => 'integer',
        'quantity' => 'integer',
        'standalone_quantity' => 'integer',
        'passage_quantity' => 'integer',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }
}
