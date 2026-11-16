<?php

namespace App\Http\Middleware;

use App\Helpers\AdminCookie;
use App\Models\Client;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Lets a client act on their own account. With the "team" option
 * (verify.client:team), an active staff member may act on any client.
 */
class VerifyClientToken
{
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        $token = $request->bearerToken();

        if (!$token && in_array('team', $options, true) && ($token = AdminCookie::token($request))
            && !AdminCookie::passesCsrfCheck($request)) {
            return $this->deny('Missing X-Requested-With header.', 403);
        }

        if (!$token) {
            return $this->deny('Access token is required.', 401);
        }

        try {
            $payload = JWTAuth::setToken($token)->getPayload();
        } catch (JWTException $e) {
            return $this->deny('Invalid or expired access token.', 401);
        }

        if (in_array('team', $options, true) && $payload->get('team_id')) {
            return $this->asTeam($request, $next, $payload->get('team_id'), $payload->get('iat'));
        }

        $clientId = $payload->get('client_id');
        $client = $clientId ? Client::where('client_id', $clientId)->first() : null;

        if (!$client) {
            return $this->deny('Invalid access token.', 401);
        }

        if ($client->tokenPredatesPasswordChange($payload->get('iat'))) {
            return $this->deny('Your password was changed. Please log in again.', 401);
        }

        if ($request->filled('client_id') && $request->input('client_id') !== $client->client_id) {
            return $this->deny('You can only act on your own account.', 403);
        }

        $request->attributes->set('client', $client);

        return $next($request);
    }

    private function asTeam(Request $request, Closure $next, string $teamId, mixed $issuedAt): Response
    {
        $team = Team::with('currentStatus')->where('team_id', $teamId)->first();

        if (!$team || $team->tokenPredatesPasswordChange($issuedAt)) {
            return $this->deny('Invalid or expired access token.', 401);
        }

        if ($team->isSuspended()) {
            return $this->deny('Your account has been suspended.', 403);
        }

        $request->attributes->set('team', $team);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
