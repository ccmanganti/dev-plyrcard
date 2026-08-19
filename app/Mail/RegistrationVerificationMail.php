<?php

namespace App\Mail;

use App\Models\User;
use App\Support\PlyrcardMailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $verificationUrl) {}

    public function envelope(): Envelope
    {
        $support = PlyrcardMailSender::address();

        return new Envelope(
            from: $support,
            replyTo: [$support],
            subject: 'Confirm your PLYRCARD account',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.plyrcard-registration-verification');
    }
}