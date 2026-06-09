<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceDetectionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'proctoring_session_id',
        'device_type',
        'device_name',
        'detected_at',
        'description',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    // العلاقات
    public function proctoringSession(): BelongsTo
    {
        return $this->belongsTo(ProctoringSession::class);
    }

    // Scopes
    public function scopePhone($query)
    {
        return $query->where('device_type', 'phone');
    }

    public function scopeCamera($query)
    {
        return $query->where('device_type', 'camera');
    }

    public function scopeTablet($query)
    {
        return $query->where('device_type', 'tablet');
    }

    public function scopeEarDevice($query)
    {
        return $query->where('device_type', 'ear_device');
    }

    // Methods
    public function getDeviceTypeLabel(): string
    {
        return match($this->device_type) {
            'camera' => 'كاميرا',
            'phone' => 'هاتف',
            'tablet' => 'جهاز لوحي',
            'ear_device' => 'سماعة أذن',
            'usb_device' => 'جهاز USB',
            default => 'جهاز آخر',
        };
    }
}
