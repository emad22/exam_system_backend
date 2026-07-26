<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use LogsActivity;

    protected $appends = ['media_url', 'audio_url', 'image_url', 'pdf_url'];

    protected $fillable = [
        'skill_id', 
        'exam_id',
        'level_id',
        'passage_id',
        'type', 
        'instructions',
        'general_instructions',
        'content', 
        'media_path', 
        'audio_path',
        'image_path',
        'pdf_path',
        'image_width',
        'image_height',
        'points',
        'min_words',
        'max_words',
        'sort_order',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'points' => 'integer',
        'min_words' => 'integer',
        'max_words' => 'integer',
        'level_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function passage(): BelongsTo
    {
        return $this->belongsTo(Passage::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getMediaUrlAttribute()
    {
        if ($this->media_path) {
            return asset('storage/' . $this->media_path); // ✅
        }
        return null;
    }

    public function getAudioUrlAttribute()
    {
        if ($this->audio_path) {
            return asset('storage/' . $this->audio_path); // ✅
        }
        return null;
    }

    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return asset('storage/' . $this->image_path); // ✅ already correct
        }
        return null;
    }

    public function getPdfUrlAttribute()
    {
        if ($this->id && ($this->pdf_path || ($this->media_path && str_ends_with(strtolower($this->media_path), '.pdf')))) {
            return url("api/v1/questions/{$this->id}/pdf");
        }
        if ($this->pdf_path) {
            return asset('storage/' . $this->pdf_path);
        }
        if ($this->media_path && str_ends_with(strtolower($this->media_path), '.pdf')) {
            return asset('storage/' . $this->media_path);
        }
        return null;
    }
}
