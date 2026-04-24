<?php

namespace App\Notifications;

use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WebsitePublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Website $website
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteUrl = $this->website->domain
            ? 'https://' . $this->website->domain
            : url('/' . $this->website->slug);

        return (new MailMessage)
            ->subject('Your PLYRCARD site is now live')
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line('Great news — your PLYRCARD site has been reviewed, approved, and published.')
            ->line('Your site is now live and ready to be viewed.')
            ->action('View Your Site', $siteUrl)
            ->line('If you notice anything that needs updating, please log in to your account or contact us at support@plyrcard.com.')
            ->line('Thank you for being part of PLYRCARD.')
            ->salutation('Best regards, The PLYRCARD Team');
    }
}