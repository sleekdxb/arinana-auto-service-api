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
                'footer' => 'If you have any questions, reply to this email or contact us at info@arianaautoservice.com.',
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Account email failed: ' . $e->getMessage());

            return false;
        }
    }
}
