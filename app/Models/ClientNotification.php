<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientNotification extends Model
{
    protected $table = 'client_notifications';

    protected $fillable = [
        'notif_id',
        'client_id',
        'type',
        'title',
        'body',
        'ref_id',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];
}
