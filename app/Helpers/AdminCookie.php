<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/** The HttpOnly cookie that carries a staff member's access token. */
class AdminCookie
{
    public const CSRF_HEADER = 'X-Requested-With';

    public static function make(string $token): Cookie
    {
        return self::build($token, now()->addDays(config('admin_auth.days')));
    }

    public static function forget(): Cookie
    {
        return self::build('', now()->subYear());
    }

    public static function token(Request $request): ?string
    {
        $value = $request->cookies->get(config('admin_auth.cookie'));

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** A cookie-authenticated request must carry the header only allowed origins can send. */
    public static function passesCsrfCheck(Request $request): bool
    {
        return strcasecmp((string) $request->header(self::CSRF_HEADER), 'XMLHttpRequest') === 0;
    }

    private static function build(string $value, \DateTimeInterface $expires): Cookie
    {
        return Cookie::create(
            config('admin_auth.cookie'),
            $value,
            $expires,
            '/',
            config('admin_auth.domain'),
            config('admin_auth.secure'),
            true,
            false,
            strtolower(config('admin_auth.same_site')),
        );
    }
}
