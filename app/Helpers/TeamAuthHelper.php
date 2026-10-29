<?php

namespace App\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Client;
use App\Models\ClientStatus;
use Illuminate\Support\Facades\Hash;
use App\Models\Team;
use App\Models\TeamSession;
use Tymon\JWTAuth\Facades\JWTAuth;


class TeamAuthHelper
{

    public static function login(Request $request, $otpVerified = false)
    {
        try {
            $user = Team::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $expirationTime = now()->addDays(7);

            $currentDeviceKey = md5(
                $request->ip() . $request->header('User-Agent')
            );

            $existingSessions = TeamSession::where(
                'team_id',
                $user->team_id
            )->get();

            /*
            |--------------------------------------------------------------------------
            | 1. CHECK IF DEVICE SESSION EXISTS
            |--------------------------------------------------------------------------
            */

            foreach ($existingSessions as $session) {
                $payload = json_decode($session->payload, true) ?? [];

                if (
                    !empty($payload['device_key']) &&
                    $payload['device_key'] === $currentDeviceKey
                ) {
                    /*
                    |--------------------------------------------------------------------------
                    | OTP CHECK DISABLED
                    |--------------------------------------------------------------------------
                    | The otp_pending check has been removed.
                    | Existing device sessions can now be refreshed directly.
                    */

                    /*
                    |--------------------------------------------------------------------------
                    | DEVICE FOUND - REFRESH SESSION
                    |--------------------------------------------------------------------------
                    */

                    $token = JWTAuth::customClaims([
                        'exp' => $expirationTime->timestamp
                    ])->fromUser($user);

                    $payload['access_token'] = $token;
                    $payload['ip_address'] = $request->ip();
                    $payload['user_agent'] = $request->header('User-Agent');
                    $payload['otp_pending'] = false;

                    $session->update([
                        'payload' => json_encode($payload),
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->header('User-Agent'),
                        'expires_at' => $expirationTime,
                        'last_activity' => now()->timestamp
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Session refreshed on this device',
                        'data' => [
                            'user' => [
                                'team_id' => $user->team_id,
                                'email' => $user->email,
                            ],
                            'auth' => [
                                'access_token' => $token,
                                'session_id' => $session->session_id
                            ]
                        ]
                    ], 200);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 2. NEW DEVICE - OTP CHECK DISABLED
            |--------------------------------------------------------------------------
            | No OTP verification is required for a new device.
            | The login flow continues directly to session creation.
            */

            /*
            |--------------------------------------------------------------------------
            | 3. CREATE SESSION AND ISSUE JWT
            |--------------------------------------------------------------------------
            */

            $token = JWTAuth::customClaims([
                'exp' => $expirationTime->timestamp
            ])->fromUser($user);

            $session = TeamSession::create([
                'session_id' => 'SESS_' . strtoupper(Str::random(18)),
                'team_id' => $user->team_id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'payload' => json_encode([
                    'access_token' => $token,
                    'device_key' => $currentDeviceKey,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'otp_pending' => false
                ]),
                'last_activity' => now()->timestamp,
                'expires_at' => $expirationTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'team_id' => $user->team_id,
                        'email' => $user->email,
                    ],
                    'auth' => [
                        'session_id' => $session->session_id,
                        'access_token' => $token
                    ]
                ]
            ], 200);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Login failed'
            ], 500);
        }
    }




    public static function logout(Request $request): JsonResponse
    {
        try {
            // 1. Invalidate the JWT token
            if ($token = JWTAuth::getToken()) {
                JWTAuth::invalidate($token);
            }

            // 2. Delete the team session
            $sessionId = $request->input('session_id');

            $deleted = TeamSession::where('session_id', $sessionId)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found or already deleted'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during logout'
            ], 500);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error during logout'
            ], 500);
        }
    }

    public static function reset_password(Request $request): JsonResponse
    {
        $user = Team::where('team_id', $request->team_id)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        $user->password = Hash::make($request->password);

        if (!$user->save()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ], 200);
    }


    public static function getProfile(Request $request): JsonResponse
    {
        $team = Team::with(['sessions'])
            ->where('team_id', $request->team_id)
            ->first();

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $team,
        ], 200);
    }



    public static function updateProfile(Request $request): JsonResponse
    {
        $team = Team::where('team_id', $request->team_id)->first();

        if (!$team) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);
        }

        $allowedFields = [
            'email',
            'full_name',
            'phone',
        ];

        // Get only allowed fields from the request
        $data = $request->only($allowedFields);

        // Ignore null and empty values
        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });

        // Normalize email and update its hash
        if (isset($data['email'])) {
            $data['email'] = strtolower($data['email']);
            $data['hashed_email'] = hash('sha256', $data['email']);
        }

        // Update only the provided, non-empty fields
        if (!empty($data)) {
            $team->update($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $team->fresh(),
        ], 200);
    }
}