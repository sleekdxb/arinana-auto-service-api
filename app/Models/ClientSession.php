<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientSession extends Model
{
    protected $table = 'client_sessions';

    public $timestamps = false;

    protected $fillable = [
        'client_id',
        'session_id',
        'ip_address',
        'user_agent',
        'payload',
        'expires_at',
        'last_activity',
        'fcm_token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_activity' => 'integer',
        ];
    }
}

