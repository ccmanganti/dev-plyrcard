<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Website;
use App\Support\PlyrcardMailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlayerActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $player,
        public Website $website,
        public string $activityType,
        public string $platform = 'website',
        public ?string $viewerEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->activityType === 'profile_view'
            ? 'Someone viewed your PLYRCARD'
            : 'Someone clicked your ' . ucfirst($this->platform) . ' link';

        $support = PlyrcardMailSender::address();

        return new Envelope(
            from: $support,
            replyTo: [$support],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.plyrcard-player-activity');
    }
}