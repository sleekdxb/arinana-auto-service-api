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
        $otpId = $request->otp_id;
        $code = $request->code;
        $sessionId = $request->session_id;

        $otpRecord = Otp::where('otp_id', $otpId)
            ->where('is_used', false)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP ID'
            ], 404);
        }

        if ($otpRecord->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired'
            ], 400);
        }

        if (!Hash::check($code, $otpRecord->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP code'
            ], 400);
        }

        $otpRecord->markAsUsed();

        /*
        |--------------------------------------------------------------------------
        | If session_id exists → update otp_pending and return access_token
        |--------------------------------------------------------------------------
        */

        if (!empty($sessionId)) {

            $session = ClientSession::where('session_id', $sessionId)->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found'
                ], 404);
            }

            $payload = json_decode($session->payload, true);

            if (empty($payload['access_token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access token not available in session'
                ], 400);
            }

            //  Update otp_pending to false
            $payload['otp_pending'] = false;

            $session->update([
                'payload' => json_encode($payload),
                'last_activity' => now()->timestamp
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'session_id' => $sessionId,
                    'access_token' => $payload['access_token']
                ]
            ]);
        }
        if (!empty($otpRecord->target) && $otpRecord->target === 'VERIFY_EMAIL') {
            $user = Client::where('client_id', $otpRecord->acc_id)->first();
            $sessions = ClientSession::where('client_id', $user->acc_id)->get();
            $fcmTokens = $sessions
                ->pluck('fcm_token')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            //if ($sessions && $fcmTokens) {

            //    if ($user->account_type === 'driver') {
            //       CreateDriverNotificationJob::dispatch([
            //           'staff_id' => 'SYSTEM',
            //           'acc_id' => $user->acc_id,
            //           'notifiable_id' => $user->id,
            //           'subject' => 'Email Verified',
            //           'message' => 'Your email has been verified.',
            //           'fcm_token' => $fcmTokens,
            //           'type' => 'email_verified',
            //          'data' => $otpRecord,
            //      ]);

            //    } else {

            //        CreateDealerNotificationJob::dispatch([
            //            'staff_id' => 'SYSTEM',
            //            'acc_id' => $user->acc_id,
            //            'notifiable_id' => $user->id,
            //            'subject' => 'Email Verified',
            //            'message' => 'Your email has been verified.',
            //            'fcm_token' => $fcmTokens,
            //            'type' => 'email_verified',
            //            'data' => $otpRecord,
            //        ]);
            //    }


            // }
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully'
        ]);
    }
}