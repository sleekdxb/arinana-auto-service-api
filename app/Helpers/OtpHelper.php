<?php

namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Models\Otp;
use App\Models\Client;
use Illuminate\Support\Facades\Mail;    // For Mail::send()
use App\Mail\OtpMail;
use App\Models\ClientSession;
use App\Models\ClientEmail;



use Log;


class OtpHelper
{

    public static function generateOtp(Request $request): JsonResponse
    {
        $email = strtolower($request->email);
        $target = strtoupper($request->target);

        // hash email for lookup
        $emailHash = hash('sha256', $email);

        $user = Client::where('hashed_email', $emailHash)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }

        // generate OTP
        $otpCode = rand(100000, 999999);

        // generate OTP ID
        $otpId = $target . '_' . Str::random(20);

        $expiresAt = Carbon::now()->addMinutes(10);

        // -------------------------
        // CREATE MAILABLE
        // -------------------------
        $mailable = null;

        switch ($target) {

            case 'VERIFY_EMAIL':

                if ($user->email_verified_at) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email already verified'
                    ], 400);
                }

                $mailable = new OtpMail(
                    $user->first_name . " " . $user->last_name ?? 'NON',
                    $otpCode,
                    'verify_email',
                    $user->email,
                    'Verify Your Email – Arinana Auto Dispatch'
                );

                break;

            case 'RESET_PASSWORD':

                $mailable = new OtpMail(
                    $user->first_name . " " . $user->last_name ?? 'NON',
                    $otpCode,
                    'reset_password',
                    $user->email,
                    'Your Password Reset Code – Arinana Auto Dispatch'
                );

                break;

            case 'UN_AUTH_DEVICE':

                $mailable = new OtpMail(
                    $user->first_name . " " . $user->last_name ?? 'NON',
                    $otpCode,
                    'un_auth_device',
                    $user->email,
                    'New Device Login Verification – Arinana Auto Dispatch'
                );

                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid target'
                ], 400);
        }

        // -------------------------
        // SEND EMAIL
        // -------------------------
        Mail::send($mailable);

        // -------------------------
        // LOG EMAIL (SAFE)
        // -------------------------
        ClientEmail::createEmail([
            'sender_id' => 'SYSTEM001',
            'receiver_id' => $user->client_id,
            'subject' => $mailable->subject ?? 'OTP EMAIL',
            'message' => json_encode([
                'otp' => $otpCode,
                'target' => $target,
                'email' => $user->email
            ]),
        ]);

        // -------------------------
        // STORE OTP (HASHED)
        // -------------------------
        Otp::create([
            'acc_id' => $user->client_id,
            'otp_id' => $otpId,
            'otp' => Hash::make($otpCode),
            'target' => $target,
            'is_used' => false,
            'expires_at' => $expiresAt,
        ]);

        // -------------------------
        // RESPONSE
        // -------------------------
        return response()->json([
            'success' => true,
            'message' => 'OTP generated successfully',
            'data' => [
                'otp_id' => $otpId,
                'expires_at' => $expiresAt
            ]
        ]);
    }

    public static function verifyOtp(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'otp_id' => 'required|string',
                'code' => 'required|string',
                'session_id' => 'nullable|string',
            ]);

            $otpId = $request->input('otp_id');
            $code = $request->input('code');
            $sessionId = $request->input('session_id');

            // 1. Find an unused OTP.
            $otpRecord = Otp::where('otp_id', $otpId)
                ->where('is_used', false)
                ->first();

            if (!$otpRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP ID or OTP already used.',
                ], 404);
            }

            // 2. Check expiration.
            if ($otpRecord->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP expired. Please request a new OTP.',
                ], 400);
            }

            // 3. Verify the submitted OTP.
            if (!Hash::check($code, $otpRecord->otp)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP code.',
                ], 400);
            }

            /*
             * 4. Handle new-device login verification.
             */
            if ($otpRecord->target === 'UN_AUTH_DEVICE') {

                if (empty($sessionId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session ID is required for device verification.',
                    ], 400);
                }

                // Ensure this session belongs to the OTP's account.
                $session = ClientSession::where('session_id', $sessionId)
                    ->where('client_id', $otpRecord->acc_id)
                    ->first();

                if (!$session) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session not found.',
                    ], 404);
                }

                $payload = json_decode($session->payload, true);

                if (!is_array($payload)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid session payload.',
                    ], 400);
                }

                // The session must actually be awaiting OTP.
                if (($payload['otp_pending'] ?? false) !== true) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This session is not awaiting OTP verification.',
                    ], 400);
                }

                // Check that the session has not expired.
                if (
                    !$session->expires_at ||
                    Carbon::parse($session->expires_at)->isPast()
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session expired. Please log in again.',
                    ], 401);
                }

                /*
                 * Consume the OTP and update the session atomically.
                 * Lock the records to prevent concurrent reuse.
                 */
                $result = \Illuminate\Support\Facades\DB::transaction(
                    function () use ($otpRecord, $session) {

                        $lockedOtp = Otp::where('otp_id', $otpRecord->otp_id)
                            ->where('is_used', false)
                            ->lockForUpdate()
                            ->first();

                        if (
                            !$lockedOtp ||
                            $lockedOtp->isExpired() ||
                            !Hash::check(
                                request()->input('code'),
                                $lockedOtp->otp
                            )
                        ) {
                            return false;
                        }

                        $lockedSession = ClientSession::where(
                            'session_id',
                            $session->session_id
                        )
                            ->where('client_id', $lockedOtp->acc_id)
                            ->lockForUpdate()
                            ->first();

                        if (!$lockedSession) {
                            return false;
                        }

                        $payload = json_decode(
                            $lockedSession->payload,
                            true
                        );

                        if (
                            !is_array($payload) ||
                            ($payload['otp_pending'] ?? false) !== true ||
                            !$lockedSession->expires_at ||
                            Carbon::parse(
                                $lockedSession->expires_at
                            )->isPast()
                        ) {
                            return false;
                        }

                        // Do not issue or expose a token until verification.
                        $payload['otp_pending'] = false;

                        // Remove any JWT created before OTP verification.
                        unset($payload['access_token']);

                        $lockedOtp->is_used = true;
                        $lockedOtp->save();

                        $lockedSession->payload = json_encode($payload);
                        $lockedSession->last_activity = now()->timestamp;
                        $lockedSession->save();

                        return true;
                    }
                );

                if (!$result) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP verification failed or the session has changed. Please try again.',
                    ], 400);
                }

                // Generate a fresh token only after successful OTP verification.
                $user = Client::where(
                    'client_id',
                    $otpRecord->acc_id
                )->first();

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Account not found.',
                    ], 404);
                }

                $expirationTime = now()->addDays(7);

                $accessToken = \Tymon\JWTAuth\Facades\JWTAuth::customClaims([
                    'exp' => $expirationTime->timestamp,
                ])->fromUser($user);

                // Store the new token in the verified session.
                $payload = json_decode($session->fresh()->payload, true) ?? [];
                $payload['otp_pending'] = false;
                $payload['access_token'] = $accessToken;

                $session->update([
                    'payload' => json_encode($payload),
                    'expires_at' => $expirationTime,
                    'last_activity' => now()->timestamp,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP verified successfully. Device verified.',
                    'data' => [
                        'session_id' => $session->session_id,
                        'access_token' => $accessToken,
                    ],
                ], 200);
            }

            /*
             * 5. Handle email verification.
             */
            if ($otpRecord->target === 'VERIFY_EMAIL') {

                $user = Client::where(
                    'client_id',
                    $otpRecord->acc_id
                )->first();

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Account not found.',
                    ], 404);
                }

                if (!$user->email_verified_at) {
                    $user->email_verified_at = now();
                    $user->save();
                }
            }

            /*
             * 6. Handle other OTP purposes, such as password reset.
             * Their specific business logic should be implemented
             * in the corresponding workflow.
             */

            $otpRecord->markAsUsed();

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully.',
                'data' => [
                    'target' => $otpRecord->target,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                'OTP verification failed',
                ['error' => $e->getMessage()]
            );

            return response()->json([
                'success' => false,
                'message' => 'Unable to verify OTP. Please try again.',
            ], 500);
        }
    }
}