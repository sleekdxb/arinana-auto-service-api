<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Client extends Authenticatable implements JWTSubject
{
    protected $fillable = [
        'client_id',
        'email',
        'hashed_email',
        'first_name',
        'last_name',
        'account_type',
        'email_verified_at',
        'phone',
        'state_id',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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
            'client_id' => $this->client_id,
            'account_type' => $this->account_type,
        ];
    }
}

