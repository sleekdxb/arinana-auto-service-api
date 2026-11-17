<?php

/*
 * The admin dashboard keeps its access token in an HttpOnly cookie, so
 * page scripts (and XSS) can't read it. Requests that authenticate with the
 * cookie must also send "X-Requested-With: XMLHttpRequest"; browsers only
 * allow that header cross-origin for origins listed in config/cors.php,
 * which blocks CSRF.
 *
 * Same-site deployments (dashboard and API under arianaautoservice.com)
 * can use "lax". A dashboard on another site, or local development against
 * the hosted API, needs "none" (which also requires a secure cookie).
 */
return [
    'cookie' => env('ADMIN_COOKIE_NAME', 'ariana_admin_token'),
    'domain' => env('ADMIN_COOKIE_DOMAIN'),
    'same_site' => env('ADMIN_COOKIE_SAMESITE', 'lax'),
    'secure' => (bool) env('ADMIN_COOKIE_SECURE', true),
    'days' => 7,
];
