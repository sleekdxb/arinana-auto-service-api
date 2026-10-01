<?php

namespace App\Listeners;

use App\Events\ClientRegistered;
use App\Mail\ClientRegistrationMail;
use App\Models\ClientEmail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

class SendClientRegistrationEmail implements ShouldQueueAfterCommit
{
    public function handle(ClientRegistered $event): void
    {
        $data = $event->data;

        /*
         * Create email record before sending.
         */
        $clientEmail = ClientEmail::create([
            'receiver_id' => $data['client_id'],
            'sender_id' => $data['sender_id'] ?? null,
            'mail_id' => $data['mail_id'] ?? null,
            'subject' => 'Registration Successful',
            'message' => $data['message'] ?? 'Your registration was successful.',
            'is_sent' => false,
        ]);

        /*
         * Send registration email.
         */
        Mail::to($data['email'])
            ->send(
                new ClientRegistrationMail(
                    data: $data
                )
            );

        /*
         * Mark email as sent only after successful sending.
         */
        if ($clientEmail){
            $clientEmail->update([
            'is_sent' => true,
        ]);
        }
        
    }
}

