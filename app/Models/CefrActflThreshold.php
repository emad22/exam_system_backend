<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CefrActflThreshold extends Model
{
    protected $fillable = [
        'skill_group',
        'framework',
        'min_score',
        'level_label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'min_score'  => 'integer',
        'sort_order' => 'integer',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGroup($query, string $group)
    {
        return $query->where('skill_group', $group);
    }

    public function scopeFramework($query, string $framework)
    {
        return $query->where('framework', $framework);
    }

    // ── Constants ────────────────────────────────────────────────────────────

    public const GROUPS = ['core', 'productive'];
    public const FRAMEWORKS = ['cefr', 'actfl'];
}
