<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CoachAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $coach,
        public string $plainPassword,
        public string $loginUrl,
        public string $accessTitle,
    ) {}

    public function build(): self
    {
        return $this->subject('Your PlyrCard coach account is ready')
            ->view('emails.coach-account-created');
    }
}
