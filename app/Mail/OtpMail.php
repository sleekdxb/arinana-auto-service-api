<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use SerializesModels;

    public $name;
    public $code;
    public $type;
    public $subjectLine;
    public $toEmail;

    /**
     * @param string $name - Recipient name
     * @param string $code - OTP or reset code
     * @param string $type - verify_email | reset_password | un_auth_device
     * @param string $toEmail - Recipient email
     * @param string|null $subjectLine - optional subject
     */
    public function __construct(
        string $name,
        string $code,
        string $type,
        string $toEmail,
        string $subjectLine = null
    ) {
        $this->name = $name;
        $this->code = $code;
        $this->type = $type;
        $this->toEmail = $toEmail;
        $this->subjectLine = $subjectLine;
    }

    public function build()
    {
        // Determine subject
        if ($this->subjectLine) {
            $subject = $this->subjectLine;
        } else {

            switch ($this->type) {

                case 'verify_email':
                    $subject = 'Verify Your Email – Arinana Auto Service';
                    break;

                case 'reset_password':
                    $subject = 'Password Reset Request – Arinana Auto Service';
                    break;

                case 'un_auth_device':
                    $subject = 'New Device Login Verification – Arinana Auto Service';
                    break;

                default:
                    $subject = 'Your Verification Code – Arinana Auto Service';
                    break;
            }
        }

        return $this->from(
            config('mail.from.address'),
            config('mail.from.name')
        )
            ->to($this->toEmail)
            ->subject($subject)
            ->view('emails.otp');
    }
}