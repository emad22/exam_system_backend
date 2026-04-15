<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name',
        'skills_count',
        'description',
        'wp_package_id',
        'exam_id',
        'skills',
    ];

    protected $casts = [
        'skills' => 'array',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
