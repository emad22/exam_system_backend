<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    protected $fillable = ['name', 'description', 'icon'];

    public function levels(): HasMany
    {
        return $this->hasMany(Level::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function exams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_skill')
            ->withPivot('duration', 'is_optional')
            ->withTimestamps();
    }
}
