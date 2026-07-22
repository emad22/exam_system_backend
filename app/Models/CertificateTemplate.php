<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = ['name', 'background_image', 'content_html', 'is_default', 'elements_json', 'background_settings'];

    protected $casts = [
        'is_default' => 'boolean',
        'elements_json' => 'array',
        'background_settings' => 'array',
    ];
    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }
}
