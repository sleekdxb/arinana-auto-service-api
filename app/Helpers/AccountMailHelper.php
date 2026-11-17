<?php

namespace App\Helpers;

use App\Mail\BookingUpdateMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountMailHelper
{
    /** Sends a branded email; returns false instead of failing the request. */
    public static function send(string $to, ?string $name, string $subject, string $heading, ?string $message, array $extra = []): bool
    {
        try {
            Mail::to($to)->send(new BookingUpdateMail($subject, $extra + [
                'name' => strtok((string) $name, ' ') ?: 'there',
                'heading' => $heading,
                'message' => $message,
                'note' => null,
                'status' => null,
                'rows' => [],
                'footer' => 'account',
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Account email failed: ' . $e->getMessage());

            return false;
        }
    }

    public static function passwordChanged(string $to, ?string $name, bool $staff = false): bool
    {
        $support = config('mail.brand.support_address');

        return self::send($to, $name, $staff ? 'Your Ariana staff password was changed' : 'Your Ariana Auto Service password was changed', 'Your password was changed',
            $staff
                ? 'The password for your Ariana admin dashboard account was just changed. Other devices have been signed out.'
                : 'The password for your Ariana Auto Service account was just changed. Other devices have been signed out.', [
                'eyebrow' => 'Security alert',
                'rows' => [
                    ['When', now()->timezone(config('booking.timezone'))->format('D, M j, Y, g:i A')],
                    ['Account', $to],
                ],
                'callouts' => [
                    ['title' => 'This was you?', 'text' => "There's nothing more to do."],
                    [
                        'tone' => 'alert',
                        'title' => "This wasn't you?",
                        'text' => $staff
                            ? 'Tell a super admin right away so they can reset your password.'
                            : 'Open the app, tap Log in › Forgot password and set a new one, then email us at ' . $support . '.',
                    ],
                ],
                'footer' => $staff ? 'staff' : 'account',
            ]);
    }
}
