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

            $isFirstLogin = $existingSessions->isEmpty();

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
                    | OTP STILL PENDING
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !empty($payload['otp_pending']) &&
                        $payload['otp_pending'] === true
                    ) {
                        return response()->json([
                            'success' => false,
                            'message' => 'OTP verification required for this device',
                            'data' => [
                                'user' => [
                                    'team_id' => $user->team_id,
                                    'email' => $user->email,
                                ],
                                'auth' => [
                                    'session_id' => $session->session_id
                                ]
                            ]
                        ], 403);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | DEVICE VERIFIED - REFRESH SESSION
                    |--------------------------------------------------------------------------
                    */

                    $token = JWTAuth::customClaims([
                        'exp' => $expirationTime->timestamp
                    ])->fromUser($user);

                    $payload['access_token'] = $token;
                    $payload['ip_address'] = $request->ip();
                    $payload['user_agent'] = $request->header('User-Agent');

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
            | 2. NEW DEVICE - REQUIRE OTP
            |--------------------------------------------------------------------------
            */

            if (!$isFirstLogin && !$otpVerified) {
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
                        'otp_pending' => true
                    ]),
                    'last_activity' => now()->timestamp,
                    'expires_at' => $expirationTime
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'OTP verification required',
                    'data' => [
                        'user' => [
                            'team_id' => $user->team_id,
                            'email' => $user->email,
                        ],
                        'auth' => [
                            'session_id' => $session->session_id
                        ]
                    ]
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | 3. FIRST LOGIN OR OTP VERIFIED
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

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Login failed'
            ], 500);
        }
    }





}