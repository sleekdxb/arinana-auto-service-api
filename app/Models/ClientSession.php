<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientSession extends Model
{
    protected $table = 'client_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'client_id',
        'session_id',
        'ip_address',
        'user_agent',
        'payload',
        'expires_at',
        'last_activity',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_activity' => 'integer',
        ];
    }
}

