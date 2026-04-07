<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ExamSkill extends Pivot
{
    protected $table = 'exam_skill';

    protected $fillable = [
        'exam_id',
        'skill_id',
        'duration',
        'is_optional',
    ];
}
