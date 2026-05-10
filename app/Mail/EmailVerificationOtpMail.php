<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 10;

    public function __construct(
        public User $user,
        public string $otp
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SwapShip Email Verification OTP'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verify-otp',
            with: [
                'name' => $this->user->name,
                'otp' => $this->otp,
            ]
        );
    }
}
