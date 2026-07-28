<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partner extends Model
{
    protected $fillable = [
        'user_id',
        'partner_name',
        'website',
        'r_date',
        'note',
        'proctoring_required',
        'proctoring_mode',
    ];

    protected $casts = [
        'proctoring_required' => 'boolean',
    ];

    protected $appends = [
        'requires_identity_verification',
        'requires_live_proctoring',
    ];

    public function getProctoringModeAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }
        // Fallback for legacy database rows where proctoring_mode is not yet populated
        return !empty($this->attributes['proctoring_required']) ? 'full' : 'none';
    }

    public function getRequiresIdentityVerificationAttribute(): bool
    {
        $mode = $this->proctoring_mode;
        return in_array($mode, ['full', 'identity_only'], true);
    }

    public function getRequiresLiveProctoringAttribute(): bool
    {
        return $this->proctoring_mode === 'full';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

