<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRecording extends Model
{
    protected $fillable = [
        'proctoring_session_id',
        'student_id',
        'recording_type',
        'file_path',
        'file_size',
        'file_duration_seconds',
        'file_format',
        'storage_provider',
        'storage_path',
        'status',
        'processing_started_at',
        'processing_completed_at',
        'processing_error',
        'transcription',
        'thumbnail_url',
    ];

    protected $casts = [
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
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

    // Scopes
    public function scopeVideo($query)
    {
        return $query->where('recording_type', 'video');
    }

    public function scopeScreen($query)
    {
        return $query->where('recording_type', 'screen');
    }

    public function scopeAudio($query)
    {
        return $query->where('recording_type', 'audio');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    // Methods
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'processing_started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processing_completed_at' => now(),
        ]);
    }

    public function markAsError($error): void
    {
        $this->update([
            'status' => 'processing',
            'processing_error' => $error,
        ]);
    }

    public function getFileSizeMbAttribute(): string
    {
        if (!$this->file_size) {
            return '0 MB';
        }

        $mb = $this->file_size / (1024 * 1024);
        return number_format($mb, 2) . ' MB';
    }

    public function getDurationMinutesAttribute(): string
    {
        if (!$this->file_duration_seconds) {
            return '0:00';
        }

        $minutes = intval($this->file_duration_seconds / 60);
        $seconds = $this->file_duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
