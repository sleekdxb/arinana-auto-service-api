<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'con_id',
        'name',
        'email',
        'interested',
        'state_id',
        'message',
    ];
}