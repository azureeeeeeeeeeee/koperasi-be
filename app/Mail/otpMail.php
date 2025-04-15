<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class otpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $verificationToken;
    public $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($verificationToken)
    {
        $this->verificationToken = $verificationToken;
        $this->verificationUrl = url('/api/auth/verify/' . $verificationToken);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verifikasi Email Anda',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'verificationUrl' => $this->verificationUrl,
                'verificationToken' => $this->verificationToken,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}