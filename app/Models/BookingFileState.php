<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingFileState extends Model
{
    protected $table = 'booking_file_states';

    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'team_id',
        'state',
        'code',
        'create_at',
        'update_at',
    ];
}