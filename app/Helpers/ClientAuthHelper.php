<?php

namespace App\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Client;
use App\Models\ClientStatus;
use Illuminate\Support\Facades\Hash;
use App\Events\ClientRegistered;
use App\Models\ClientSession;

class ClientAuthHelper
{
    public static function register(Request $request): JsonResponse
    {
        try {

            // Hash of the incoming email for checking duplicates
            $emailHash = hash('sha256', $request->email);

            // Check if user with same hashed email already exists
            if (Client::where('hashed_email', $emailHash)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already exists'
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | Generate Account ID
            |--------------------------------------------------------------------------
            */
            $client_id = 'CLIENT' . strtoupper(Str::random(18));



            /*
            |--------------------------------------------------------------------------
            | Create User
            |--------------------------------------------------------------------------
            */
            $state_id = 'STATE_' . strtoupper(Str::random(15));

            $user = Client::create([
                'client_id' => $client_id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'hashed_email' => $emailHash,
                'state_id' => $state_id,
                'account_type' => $request->account_type,
                'password' => Hash::make($request->password),
            ]);

            if ($user) {

                $state = ClientStatus::create([
                    'client_id' => $client_id,
                    'state_id' => $state_id,
                    'name' => 'Active',
                    'code' => 'ACTIVE421',
                    'note' => 'Account approved',
                ]);

                //  Update user with state_id

            }

            if ($user) {
                ClientRegistered::dispatch([
                    'client_id' => $user->client_id,

                    'email' => $user->email,

                    'first_name' => $user->first_name,

                    'last_name' => $user->last_name,

                    'account_type' => $user->account_type,

                    'sender_id' => 'SYSTEM-' . now()->format('Y-m-d-H-i-s'),

                    'mail_id' => 'MAIL-' . strtoupper(Str::uuid()),

                    'message' => 'Your registration has been successfully completed.',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => [
                    'client_id' => $user->client_id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'account_type' => $user->account_type,
                    'state' => [
                        'state_id' => $state->state_id,
                        'name' => $state->name,
                        'code' => $state->code
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);

        }
    }


    public static function login(Request $request, $otpVerified = false)
    {
        try {

            $emailHash = hash('sha256', $request->email);
            $user = User::where('hashed_email', $emailHash)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not found.'
                ], 401);
            }
            $state = ClientStatus::where('state_id', $user->state_id)->first();


            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }



            $expirationTime = now()->addDays(7);
            $currentDeviceKey = md5($request->ip() . $request->header('User-Agent'));

            $existingSessions = Session::where('acc_id', $user->acc_id)->get();
            $isFirstLogin = $existingSessions->isEmpty();

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ CHECK IF DEVICE SESSION EXISTS
            |--------------------------------------------------------------------------
            */

            foreach ($existingSessions as $session) {

                $payload = json_decode($session->payload, true);

                if (!empty($payload['device_key']) && $payload['device_key'] === $currentDeviceKey) {

                    /*
                    |--------------------------------------------------------------------------
                    | OTP STILL PENDING → REQUIRE OTP
                    |--------------------------------------------------------------------------
                    */

                    if (!empty($payload['otp_pending']) && $payload['otp_pending'] === true) {

                        $sessions = Session::where('acc_id', $user->acc_id)->get();
                        $fcmTokens = $sessions->pluck('fcm_token')->filter()->unique()->values()->toArray();
                        if ($sessions && $fcmTokens) {




                        }

                        return response()->json([
                            'success' => false,
                            'message' => 'OTP verification required for this device',
                            'data' => [
                                'user' => [
                                    'acc_id' => $user->acc_id,
                                    'full_name' => $user->full_name,
                                    'email' => $user->email,
                                    'account_type' => $user->account_type,
                                    'state' => $state,
                                ],
                                'auth' => [
                                    'session_id' => $session->session_id
                                ]
                            ]
                        ], 403);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | DEVICE VERIFIED → ALLOW LOGIN
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
                        'expires_at' => $expirationTime,
                        'last_activity' => now()->timestamp
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Session refreshed on this device',
                        'data' => [
                            'user' => [
                                'acc_id' => $user->acc_id,
                                'full_name' => $user->full_name,
                                'email' => $user->email,
                                'account_type' => $user->account_type,
                                'state' => $state,
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
            | 2️⃣ NEW DEVICE → REQUIRE OTP
            |--------------------------------------------------------------------------
            */

            if (!$isFirstLogin && !$otpVerified) {
                $sessions = Session::where('acc_id', $request->acc_id)->get();

                $fcmTokens = $sessions
                    ->pluck('fcm_token')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();



                $token = JWTAuth::customClaims([
                    'exp' => $expirationTime->timestamp
                ])->fromUser($user);

                $session = Session::create([
                    'session_id' => 'SESS_' . strtoupper(Str::random(18)),
                    'acc_id' => $user->acc_id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'payload' => json_encode([
                        'access_token' => $token,
                        'device_key' => $currentDeviceKey,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->header('User-Agent'),
                        'otp_pending' => true
                    ]),
                    'fcm_token' => $request->fcm_token ?? null,
                    'last_activity' => now()->timestamp,
                    'expires_at' => $expirationTime
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'OTP verification required',
                    'data' => [
                        'user' => [
                            'client_id' => $user->client_id,
                            'full_name' => $user->full_name,
                            'email' => $user->email,
                            'account_type' => $user->account_type,
                            'state' => $state,

                        ],
                        'auth' => [
                            'session_id' => $session->session_id
                        ]
                    ]
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | 3️⃣ FIRST LOGIN OR OTP VERIFIED
            |--------------------------------------------------------------------------
            */

            $token = JWTAuth::customClaims([
                'exp' => $expirationTime->timestamp
            ])->fromUser($user);

            $session = Session::create([
                'session_id' => 'SESS_' . strtoupper(Str::random(18)),
                'acc_id' => $user->acc_id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'payload' => json_encode([
                    'access_token' => $token,
                    'device_key' => $currentDeviceKey,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'otp_pending' => false
                ]),
                'fcm_token' => $request->fcm_token ?? null,
                'last_activity' => now()->timestamp,
                'expires_at' => $expirationTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'acc_id' => $user->acc_id,
                        'full_name' => $user->full_name,
                        'email' => $user->email,
                        'account_type' => $user->account_type,
                        'state' => $state,

                    ],
                    'auth' => [
                        'session_id' => $session->session_id,
                        'access_token' => $token
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public static function reset_password(Request $request): JsonResponse
    {
        $email = strtolower($request->email ?? '');
        $password = $request->password;

        // Generate the hashed email to match database
        $emailHash = hash('sha256', $email);

        // Find the user by hashed_email
        $user = User::where('hashed_email', $emailHash)->first();
        $sessions = Session::where('acc_id', $user->acc_id)->get();

        $fcmTokens = $sessions
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Hash and update password
        $user->password = Hash::make($password);
        $isSuccess = $user->save();
        if ($isSuccess) {

        }

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ]);
    }



    public static function logout(Request $request)
    {
        try {
            // 1. Invalidate the JWT Token (Add to blacklist)
            // This ensures the token cannot be used even if it hasn't expired yet
            if ($token = JWTAuth::getToken()) {
                JWTAuth::invalidate($token);
            }

            // 2. Delete the Session from Database
            $sessionId = $request->input('session_id');
            $deleted = Session::where('session_id', $sessionId)->delete();

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

        } catch (JWTException $e) {
            // Even if JWT fails, we should try to finish the logout
            return response()->json([
                'success' => false,
                'message' => 'Error during logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}