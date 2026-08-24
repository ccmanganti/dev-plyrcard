<?php

namespace App\Mail;

use App\Models\User;
use App\Support\PlyrcardMailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Legacy class name retained for compatibility only.
 *
 * Email verification is no longer part of PLYRCARD registration. Any older
 * code that still instantiates this class now produces the same dashboard
 * welcome email instead of a "Confirm email" message.
 */
class RegistrationVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $verificationUrl = '') {}

    public function envelope(): Envelope
    {
        $support = PlyrcardMailSender::address();

        return new Envelope(
            from: $support,
            replyTo: [$support],
            subject: 'Welcome to PLYRCARD',
        );
    }

    public function content(): Content
    {
        $this->user->loadMissing('activeWebsite', 'billingInformation');

        return new Content(
            view: 'emails.plyrcard-registration-welcome',
            with: [
                'user' => $this->user,
                'dashboardUrl' => url('/admin'),
                'planKey' => $this->user->billingInformation?->plan_key,
                'isMyJourney' => strtolower(trim((string) ($this->user->billingInformation?->plan_key))) === 'my-journey',
            ],
        );
    }
}