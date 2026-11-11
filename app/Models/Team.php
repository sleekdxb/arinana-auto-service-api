<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Team extends Model implements JWTSubject
{
    protected $fillable = [
        'team_id',
        'email',
        'hashed_email',
        'full_name',
        'account_type',
        'role',
        'access_level',
        'employee_id',
        'email_verified_at',
        'phone',
        'state_id',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'hashed_email',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier stored in the JWT subject claim.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return custom JWT claims.
     */
    public function getJWTCustomClaims(): array
    {
        return ['team_id' => $this->team_id];
    }

    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const STAFF = 'STAFF';
    public const MANAGEMENT = 'MANAGEMENT';
    public const ACCESS_LEVELS = [self::SUPER_ADMIN, self::STAFF, self::MANAGEMENT];

    public function currentStatus(): BelongsTo
    {
        return $this->belongsTo(TeamStatus::class, 'state_id', 'state_id');
    }

    public function isSuspended(): bool
    {
        return $this->currentStatus?->name === TeamStatus::SUSPENDED;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(
            TeamSession::class,
            'team_id',
            'team_id'
        );
    }


}