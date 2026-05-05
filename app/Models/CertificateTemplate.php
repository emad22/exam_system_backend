<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    protected $fillable = ['name', 'background_image', 'content_html', 'is_default'];

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }
}
