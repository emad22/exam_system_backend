<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentExamConfig extends Model
{
    protected $fillable = [
        'student_id', 
        'exam_id', 
        'want_reading', 
        'want_listening', 
        'want_grammar', 
        'want_writing', 
        'want_speaking'
    ];

    protected $casts = [
        'want_reading' => 'boolean',
        'want_listening' => 'boolean',
        'want_grammar' => 'boolean',
        'want_writing' => 'boolean',
        'want_speaking' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
