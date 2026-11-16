<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Otp extends Model
{
    /** Issued once a password reset code is verified; spent by reset_password. */
    public const RESET_TOKEN = 'RESET_TOKEN';

    public const MAX_ATTEMPTS = 5;

    public const RESET_TOKEN_MINUTES = 15;

    protected $table = 'otps';

    protected $fillable = [
        'acc_id',
        'otp_id',
        'otp',
        'target',
        'is_used',
        'attempts',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** Returns the token to hand to the client, in the form "<otp_id>.<secret>". */
    public static function issueResetToken(string $accId): string
    {
        self::where('acc_id', $accId)
            ->where('target', self::RESET_TOKEN)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        $id = self::RESET_TOKEN . '_' . Str::random(24);
        $secret = Str::random(48);

        self::create([
            'acc_id' => $accId,
            'otp_id' => $id,
            'otp' => Hash::make($secret),
            'target' => self::RESET_TOKEN,
            'is_used' => false,
            'expires_at' => now()->addMinutes(self::RESET_TOKEN_MINUTES),
        ]);

        return $id . '.' . $secret;
    }

    /** Locks and returns the unused, unexpired reset token matching [token], or null. Call inside a transaction. */
    public static function lockResetToken(string $token): ?self
    {
        [$id, $secret] = array_pad(explode('.', $token, 2), 2, '');

        if (!str_starts_with($id, self::RESET_TOKEN . '_') || $secret === '') {
            return null;
        }

        $record = self::where('otp_id', $id)
            ->where('target', self::RESET_TOKEN)
            ->where('is_used', false)
            ->lockForUpdate()
            ->first();

        if (!$record || $record->isExpired() || !Hash::check($secret, $record->otp)) {
            return null;
        }

        return $record;
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

