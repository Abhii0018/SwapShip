<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $otp,
        public int $expiresInMinutes = 15
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SwapShip Password Reset OTP'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-reset-otp',
            with: [
                'name' => $this->user->name,
                'otp' => $this->otp,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]
        );
    }
}
