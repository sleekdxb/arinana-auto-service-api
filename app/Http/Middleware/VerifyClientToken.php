<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class VerifyClientToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Access token is required.',
            ], 401);
        }

        try {
            $clientId = JWTAuth::setToken($token)->getPayload()->get('client_id');
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired access token.',
            ], 401);
        }

        $client = $clientId ? Client::where('client_id', $clientId)->first() : null;

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid access token.',
            ], 401);
        }

        if ($request->filled('client_id') && $request->input('client_id') !== $client->client_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only act on your own account.',
            ], 403);
        }

        $request->attributes->set('client', $client);

        return $next($request);
    }
}
