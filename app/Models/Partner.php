<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partner extends Model
{
    protected $fillable = [
        'user_id',
        'partner_name',
        'website',
        'r_date',
        'note'
    ]; 

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
