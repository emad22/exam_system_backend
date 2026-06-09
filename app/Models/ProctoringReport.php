<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProctoringReport extends Model
{
    protected $fillable = [
        'proctoring_session_id',
        'student_id',
        'exam_id',
        'status',
        'violations_summary',
        'total_violations',
        'critical_violations',
        'high_violations',
        'medium_violations',
        'low_violations',
        'risk_score',
        'risk_level',
        'final_verdict',
        'system_notes',
        'proctor_notes',
        'analysis_json',
        'ai_insights',
        'report_pdf_url',
        'recordings_included',
        'reviewed_at',
        'reviewed_by',
        'approved_at',
        'approved_by',
        'generated_at',
    ];

    protected $casts = [
        'violations_summary' => 'array',
        'analysis_json' => 'array',
        'recordings_included' => 'boolean',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'generated_at' => 'datetime',
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

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeHighRisk($query)
    {
        return $query->where('risk_level', 'high')
                     ->orWhere('risk_level', 'critical');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeFailVerdict($query)
    {
        return $query->where('final_verdict', 'fail');
    }

    public function scopeReviewRequired($query)
    {
        return $query->where('final_verdict', 'review_required');
    }

    // Methods
    public function review($notes = null, $reviewedBy = null): void
    {
        $this->update([
            'status' => 'reviewed',
            'proctor_notes' => $notes ?? $this->proctor_notes,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
        ]);
    }

    public function approve($verdict, $approvedBy = null): void
    {
        $this->update([
            'status' => 'approved',
            'final_verdict' => $verdict,
            'approved_at' => now(),
            'approved_by' => $approvedBy,
        ]);
    }

    public function getVerdictColor(): string
    {
        return match($this->final_verdict) {
            'pass' => 'success',
            'review_required' => 'warning',
            'fail' => 'danger',
            default => 'secondary',
        };
    }

    public function getVerdictLabel(): string
    {
        return match($this->final_verdict) {
            'pass' => 'نجح',
            'review_required' => 'يتطلب مراجعة',
            'fail' => 'فشل',
            default => 'غير محدد',
        };
    }

    public function getRiskLevelColor(): string
    {
        return match($this->risk_level) {
            'low' => 'success',
            'medium' => 'info',
            'high' => 'warning',
            'critical' => 'danger',
            default => 'secondary',
        };
    }
}
