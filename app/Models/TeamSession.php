<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class TeamSession extends Authenticatable implements JWTSubject
{
    protected $table = 'team_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'team_id',
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

    /**
     * Get the identifier that will be stored in the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Custom claims to include in the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'team_id' => $this->team_id,
            'session_id' => $this->session_id,
        ];
    }
}

