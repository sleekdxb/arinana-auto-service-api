<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Otp extends Model
{
    protected $table = 'otps';

    protected $fillable = [
        'acc_id',
        'otp_id',
        'otp',
        'target',
        'is_used',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired()
    {
        return Carbon::now()->greaterThan($this->expires_at);
    }

    /**
     * Mark OTP as used
     */
    public function markAsUsed()
    {
        $this->update([
            'is_used' => true
        ]);
    }

}

