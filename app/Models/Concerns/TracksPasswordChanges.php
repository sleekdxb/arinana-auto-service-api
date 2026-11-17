<?php

namespace App\Models\Concerns;

use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Records when the password last changed, so access tokens issued before
 * then can be refused.
 */
trait TracksPasswordChanges
{
    protected static function bootTracksPasswordChanges(): void
    {
        static::saving(function (self $model) {
            if ($model->exists && $model->isDirty('password')) {
                $model->password_changed_at = now();
            }
        });
    }

    public function initializeTracksPasswordChanges(): void
    {
        $this->mergeCasts(['password_changed_at' => 'datetime']);
        $this->makeHidden('password_changed_at');
    }

    /** True when a token issued at [issuedAt] (unix seconds) predates the last password change. */
    public function tokenPredatesPasswordChange(mixed $issuedAt): bool
    {
        if (!$this->password_changed_at) {
            return false;
        }

        return !is_numeric($issuedAt) || (int) $issuedAt < $this->password_changed_at->getTimestamp();
    }

    public function freshAccessToken(int $days = 7): string
    {
        return JWTAuth::customClaims(['exp' => now()->addDays($days)->timestamp])->fromUser($this);
    }
}
