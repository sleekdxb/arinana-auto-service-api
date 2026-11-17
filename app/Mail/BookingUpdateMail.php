<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class BookingUpdateMail extends Mailable
{
    /**
     * @param array{name: string, heading: string, message: ?string, note: ?string, status: ?string, rows: array<int, array{0: string, 1: string}>} $details
     */
    public function __construct(
        public string $subjectLine,
        public array $details
    ) {
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->subjectLine)
            ->view('emails.booking-update');
    }
}
