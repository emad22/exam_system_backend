<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    //
    protected $fillable = [
    'partner_name',
    'fName_contact',
    'lName_contact',
    'email',
    'phone',
    'website',
    'country',
    'r_date',
    'is_active',
    'note'
    ];


}
