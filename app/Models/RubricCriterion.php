<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RubricCriterion extends Model
{
    use HasFactory;

    protected $table = 'rubric_criteria';

    protected $fillable = [
        'skill_type',
        'category',
        'name',
        'description',
        'percentage',
        'max_points',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'percentage' => 'float',
        'max_points' => 'float',
        'order_index' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active criteria
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific skill type
     */
    public function scopeForSkill($query, string $skillType = 'writing')
    {
        return $query->where('skill_type', $skillType);
    }
}
