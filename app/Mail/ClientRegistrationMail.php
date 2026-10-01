<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $data
    ) {
    }

    public function build()
    {
        return $this
            ->subject('Registration Successful')
            ->view('emails.client-registration');
    }
}

