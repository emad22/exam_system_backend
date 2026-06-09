<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheatingAlert extends Model
{
    protected $fillable = [
        'proctoring_session_id',
        'violation_id',
        'alert_type',
        'message',
        'severity',
        'sent_to_proctor_at',
        'proctor_acknowledged_at',
        'proctor_acknowledged_by',
    ];

    protected $casts = [
        'sent_to_proctor_at' => 'datetime',
        'proctor_acknowledged_at' => 'datetime',
    ];

    // العلاقات
    public function proctoringSession(): BelongsTo
    {
        return $this->belongsTo(ProctoringSession::class);
    }

    public function violation(): BelongsTo
    {
        return $this->belongsTo(ExamViolation::class);
    }

    public function proctorAcknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proctor_acknowledged_by');
    }

    // Scopes
    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('proctor_acknowledged_at');
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeRecent($query, $minutes = 5)
    {
        return $query->where('sent_to_proctor_at', '>=', now()->subMinutes($minutes));
    }

    // Methods
    public function acknowledge(): void
    {
        $this->update([
            'proctor_acknowledged_at' => now(),
        ]);
    }

    public function isAcknowledged(): bool
    {
        return !is_null($this->proctor_acknowledged_at);
    }
}
