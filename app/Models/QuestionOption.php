<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
        'sort_order',
        'image_path',
        'dir',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path 
            ? asset('storage/' . $this->image_path) 
            : null;
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

   
}
