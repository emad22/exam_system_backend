<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProctoringSession extends Model
{
    protected $fillable = [
        'exam_attempt_id',   // nullable – may be null for pre-exam identity verification
        'student_id',
        'proctor_id',
        'status',
        'identity_verified',
        'face_verification_score',
        'identity_verification_at',
        'ip_address',
        'user_agent',
        'device_info',
        'browser_info',
        'recording_status',
        'video_recording_id',
        'screen_recording_id',
        'audio_recording_id',
        'violations_count',
        'risk_score',
        'face_detection_alerts',
        'tab_switch_alerts',
        'copy_paste_alerts',
        'external_device_alerts',
        'started_at',
        'paused_at',
        'resumed_at',
        'ended_at',
        'duration_seconds',
        'report_status',
        'report_id',
        'final_verdict',
    ];

    protected $casts = [
        'device_info' => 'array',
        'browser_info' => 'array',
        'identity_verified' => 'boolean',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'ended_at' => 'datetime',
        'identity_verification_at' => 'datetime',
    ];

    // العلاقات
    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function proctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proctor_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(ExamViolation::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(CheatingAlert::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(ExamRecording::class);
    }

    public function faceDetectionLogs(): HasMany
    {
        return $this->hasMany(FaceDetectionLog::class);
    }

    public function deviceDetectionLogs(): HasMany
    {
        return $this->hasMany(DeviceDetectionLog::class);
    }

    public function report()
    {
        return $this->belongsTo(ProctoringReport::class, 'report_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEnded($query)
    {
        return $query->where('status', 'ended');
    }

    public function scopeHighRisk($query)
    {
        return $query->where('risk_score', '>=', 70);
    }

    // Methods
    public function getRiskLevelAttribute(): string
    {
        $score = $this->risk_score;
        
        if ($score >= 80) {
            return 'critical';
        } elseif ($score >= 60) {
            return 'high';
        } elseif ($score >= 40) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    public function getDurationMinutesAttribute(): int
    {
        if (!$this->duration_seconds) {
            return 0;
        }
        
        return (int) ($this->duration_seconds / 60);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function hasEnded(): bool
    {
        return $this->status === 'ended';
    }
}
