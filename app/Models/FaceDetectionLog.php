<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceDetectionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'proctoring_session_id',
        'student_id',
        'face_count',
        'primary_face_confidence',
        'secondary_face_detected',
        'face_lost',
        'screenshot_url',
        'timestamp',
    ];

    protected $casts = [
        'secondary_face_detected' => 'boolean',
        'face_lost' => 'boolean',
        'timestamp' => 'datetime',
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
    public function scopeMultipleFaces($query)
    {
        return $query->where('face_count', '>', 1);
    }

    public function scopeNoFace($query)
    {
        return $query->where('face_count', 0);
    }

    public function scopeWithSecondaryFace($query)
    {
        return $query->where('secondary_face_detected', true);
    }

    // Methods
    public function hasMultipleFaces(): bool
    {
        return $this->face_count > 1;
    }

    public function hasNoFace(): bool
    {
        return $this->face_count === 0;
    }
}
