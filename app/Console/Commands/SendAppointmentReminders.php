<?php

namespace App\Console\Commands;

use App\Helpers\NotificationHelper;
use App\Models\Booking;
use App\Models\BookingState;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Remind customers about bookings in the next 24 hours';

    public function handle(): int
    {
        $timezone = config('booking.timezone');
        $now = Carbon::now($timezone);
        $until = $now->copy()->addDay();
        $sent = 0;

        $bookings = Booking::with('state')
            ->whereNull('reminder_sent_at')
            ->whereDate('date', '>=', $now->toDateString())
            ->whereDate('date', '<=', $until->toDateString())
            ->get();

        foreach ($bookings as $booking) {
            if (strtoupper((string) $booking->state?->name) !== BookingState::PLACED) {
                continue;
            }

            $at = Carbon::parse(
                $booking->date->toDateString() . ' ' . substr((string) $booking->getRawOriginal('time'), 0, 5),
                $timezone
            );

            if ($at->lte($now) || $at->gt($until)) {
                continue;
            }

            NotificationHelper::appointmentReminder($booking);
            $booking->update(['reminder_sent_at' => now()]);
            $sent++;
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
