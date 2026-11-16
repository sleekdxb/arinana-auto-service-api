<?php

namespace App\Http\Middleware;

use App\Helpers\AdminCookie;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class VerifyTeamToken
{
    public function handle(Request $request, Closure $next, string ...$accessLevels): Response
    {
        $token = $request->bearerToken();

        if (!$token && ($token = AdminCookie::token($request)) && !AdminCookie::passesCsrfCheck($request)) {
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

        $teamId = $payload->get('team_id');
        $team = $teamId ? Team::with('currentStatus')->where('team_id', $teamId)->first() : null;

        if (!$team) {
            return $this->deny('Invalid access token.', 401);
        }

        if ($team->tokenPredatesPasswordChange($payload->get('iat'))) {
            return $this->deny('Your password was changed. Please log in again.', 401);
        }

        if ($team->isSuspended()) {
            return $this->deny('Your account has been suspended.', 403);
        }

        $levels = array_map('strtoupper', $accessLevels);
        if ($levels && !in_array($team->access_level, $levels, true)) {
            return $this->deny('You do not have permission to do this.', 403);
        }

        if ($request->filled('team_id') && $request->input('team_id') !== $team->team_id) {
            return $this->deny('You can only act as yourself.', 403);
        }

        $request->merge(['team_id' => $team->team_id]);
        $request->attributes->set('team', $team);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
