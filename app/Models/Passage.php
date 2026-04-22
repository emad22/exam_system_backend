<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Passage extends Model
{
    protected $appends = ['media_url'];

    protected $fillable = [
        'type',
        'title',
        'content',
        'media_path',
        'questions_limit',
        'is_random'
    ];

    protected $casts = [
        'is_random' => 'boolean',
        'questions_limit' => 'integer'
    ];

    public function getMediaUrlAttribute()
    {
        if ($this->media_path) {
            return asset('storage/' . $this->media_path);
        }
        return null;
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
