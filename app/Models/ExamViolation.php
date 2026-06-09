<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamViolation extends Model
{
    protected $fillable = [
        'proctoring_session_id',
        'student_id',
        'violation_type',
        'severity',
        'status',
        'screenshot_url',
        'video_clip_url',
        'evidence',
        'description',
        'detected_by',
        'flagged_by_proctor',
        'proctor_notes',
        'reviewed_at',
        'reviewed_by',
        'action_taken',
        'timestamp',
    ];

    protected $casts = [
        'evidence' => 'array',
        'flagged_by_proctor' => 'boolean',
        'timestamp' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // العلاقات
    public function proctoringSession(): BelongsTo
    {
        return $this->belongsTo(ProctoringSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(CheatingAlert::class);
    }

    // Scopes
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeUnreviewed($query)
    {
        return $query->whereNull('reviewed_at');
    }

    public function scopeRecent($query, $minutes = 5)
    {
        return $query->where('timestamp', '>=', now()->subMinutes($minutes));
    }

    // Methods
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isHighSeverity(): bool
    {
        return in_array($this->severity, ['critical', 'high']);
    }

    public function hasEvidence(): bool
    {
        return !is_null($this->screenshot_url) || 
               !is_null($this->video_clip_url) || 
               !empty($this->evidence);
    }

    public function getSeverityBadgeAttribute(): string
    {
        $badges = [
            'info' => 'secondary',
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
        ];

        return $badges[$this->severity] ?? 'secondary';
    }

    public function getViolationLabelAttribute(): string
    {
        $labels = [
            'multiple_faces' => 'عدة وجوه',
            'face_not_visible' => 'الوجه غير مرئي',
            'face_swap' => 'تبديل الوجه',
            'tab_switched' => 'تبديل التطبيق',
            'browser_opened' => 'فتح متصفح آخر',
            'copy_paste' => 'نسخ و لصق',
            'external_device' => 'جهاز خارجي',
            'suspicious_audio' => 'صوت مريب',
            'suspicious_behavior' => 'سلوك مريب',
            'environment_change' => 'تغيير البيئة',
            'person_in_background' => 'شخص في الخلفية',
            'phone_usage' => 'استخدام الهاتف',
            'unusual_eye_movement' => 'حركات عين غير عادية',
        ];

        return $labels[$this->violation_type] ?? $this->violation_type;
    }
}
