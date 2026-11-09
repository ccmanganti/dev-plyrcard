<?php

namespace App\Notifications;

use App\Models\PlyrLabBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlyrLabTrainerBookedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = PlyrLabBooking::query()
            ->with(['player.team', 'player.club', 'trainer'])
            ->find($this->bookingId);

        $player = $booking?->player;
        $playerName = trim((string) ($player?->first_name . ' ' . $player?->last_name)) ?: 'A PLYRCARD player';
        $team = $player?->team?->name ?: $player?->team_name ?: $player?->club?->name;
        $when = $booking?->scheduled_for?->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A');

        $mail = (new MailMessage)
            ->subject('New PLYRLab trainer booking')
            ->greeting('Hi ' . ($notifiable->first_name ?: 'Trainer') . ',')
            ->line($playerName . ' booked a PLYRLab training/performance session with you.');

        if ($when) {
            $mail->line('Scheduled for: ' . $when);
        }

        if (filled($player?->sport) || filled($team)) {
            $mail->line('Player: ' . implode(' · ', array_filter([
                $player?->sport,
                $team,
            ])));
        }

        if (filled($booking?->notes)) {
            $mail->line('Player note: ' . (string) $booking->notes);
        }

        return $mail
            ->action('Open PLYRCARD', url('/admin'))
            ->line('PLYRCARD will provision the player in your connected Plyomat account in the background.');
    }
}
