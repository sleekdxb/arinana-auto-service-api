<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountDeletionRequest extends Model
{
    public const PENDING = 'PENDING';
    public const COMPLETED = 'COMPLETED';
    public const REJECTED = 'REJECTED';
    public const CANCELLED = 'CANCELLED';

    protected $fillable = [
        'request_id',
        'client_id',
        'client_name',
        'client_email',
        'client_phone',
        'account_type',
        'reason',
        'status',
        'team_id',
        'note',
        'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function handledBy()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }
}
