<?php

namespace App\Notifications;

use App\Models\PlyrLabBooking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlyrLabTrainerBookedNotification extends Notification
{
    use Queueable;

    public function __construct(public int $bookingId, public string $event = 'booking_requested') {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = PlyrLabBooking::query()
            ->with(['player.team', 'player.club', 'trainer.user'])
            ->find($this->bookingId);

        $player = $booking?->player;
        $trainerUser = $booking?->trainer?->user;
        $playerName = trim((string) ($player?->first_name . ' ' . $player?->last_name)) ?: 'PLYRCARD player';
        $trainerName = trim((string) ($trainerUser?->first_name . ' ' . $trainerUser?->last_name)) ?: ($booking?->trainer?->displayName() ?: 'PLYRLab trainer');
        $displayTime = in_array($this->event, ['reschedule_requested'], true) && $booking?->requested_scheduled_for
            ? $booking->requested_scheduled_for
            : $booking?->scheduled_for;
        $when = $displayTime?->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A');
        $isPlayer = $player && (int) ($notifiable->getKey() ?? 0) === (int) $player->getKey();
        $isTrainer = $trainerUser && (int) ($notifiable->getKey() ?? 0) === (int) $trainerUser->getKey();

        $event = $this->event;
        $subject = match ($event) {
            'booking_requested' => 'PLYRLab booking awaiting approval',
            'booking_approved' => 'PLYRLab booking approved',
            'reschedule_requested' => 'PLYRLab reschedule awaiting approval',
            'reschedule_approved' => 'PLYRLab reschedule approved',
            'reschedule_declined' => 'PLYRLab reschedule declined',
            'booking_cancelled' => 'PLYRLab booking cancelled',
            'booking_declined' => 'PLYRLab booking declined',
            default => 'PLYRLab booking update',
        };

        $mail = (new MailMessage)->subject($subject);

        if ($isPlayer) {
            $mail->greeting('Hi ' . ($player?->first_name ?: 'Player') . ',');

            match ($event) {
                'booking_requested' => $mail->line('Your PLYRLab booking with ' . $trainerName . ' was submitted and is waiting for trainer approval.'),
                'booking_approved' => $mail->line($trainerName . ' approved your PLYRLab booking. PLYRCard can now connect or create your Plyomat athlete profile.'),
                'reschedule_requested' => $mail->line('Your updated PLYRLab schedule was sent to ' . $trainerName . ' for approval.'),
                'reschedule_approved' => $mail->line($trainerName . ' approved your updated PLYRLab schedule.'),
                'reschedule_declined' => $mail->line($trainerName . ' declined the requested schedule change. Your previously approved session remains unchanged.'),
                'booking_cancelled' => $mail->line('Your PLYRLab booking with ' . $trainerName . ' has been cancelled.'),
                'booking_declined' => $mail->line($trainerName . ' declined the pending PLYRLab booking request. You can choose another trainer or schedule.'),
                default => $mail->line('Your PLYRLab booking was updated.'),
            };
        } elseif ($isTrainer) {
            $mail->greeting('Hi ' . ($trainerUser?->first_name ?: 'Trainer') . ',');

            match ($event) {
                'booking_requested' => $mail->line($playerName . ' requested a PLYRLab session. Approve the booking before PLYRCard creates or connects the athlete in your Plyomat account.'),
                'booking_approved' => $mail->line('You approved ' . $playerName . '\'s PLYRLab booking.'),
                'reschedule_requested' => $mail->line($playerName . ' requested a new PLYRLab session time. Review and approve the change.'),
                'reschedule_approved' => $mail->line('You approved ' . $playerName . '\'s updated PLYRLab schedule.'),
                'reschedule_declined' => $mail->line('You declined ' . $playerName . '\'s requested schedule change. The previously approved session remains active.'),
                'booking_cancelled' => $mail->line($playerName . ' cancelled the PLYRLab booking.'),
                'booking_declined' => $mail->line('The pending PLYRLab booking for ' . $playerName . ' was declined.'),
                default => $mail->line($playerName . '\'s PLYRLab booking was updated.'),
            };
        } else {
            $mail->greeting('PLYRLab Admin Update');

            match ($event) {
                'booking_requested' => $mail->line($playerName . ' requested a PLYRLab booking with ' . $trainerName . '.'),
                'booking_approved' => $mail->line($trainerName . ' approved ' . $playerName . '\'s PLYRLab booking.'),
                'reschedule_requested' => $mail->line($playerName . ' requested a schedule change with ' . $trainerName . '.'),
                'reschedule_approved' => $mail->line($trainerName . ' approved ' . $playerName . '\'s schedule change.'),
                'reschedule_declined' => $mail->line($trainerName . ' declined ' . $playerName . '\'s requested schedule change.'),
                'booking_cancelled' => $mail->line($playerName . '\'s PLYRLab booking with ' . $trainerName . ' was cancelled.'),
                'booking_declined' => $mail->line($playerName . '\'s pending PLYRLab booking with ' . $trainerName . ' was declined.'),
                default => $mail->line($playerName . '\'s PLYRLab booking was updated.'),
            };
        }

        if ($when) {
            $mail->line('Scheduled for: ' . $when);
        }

        if (filled($booking?->notes)) {
            $mail->line('Player note: ' . (string) $booking->notes);
        }

        return $mail
            ->action($isPlayer ? 'Open PLYRLab' : 'Open PLYRLab Management', $isPlayer ? url('/admin/coach-database/plyrlab') : url('/admin/plyrlab'))
            ->line($event === 'booking_requested' || $event === 'reschedule_requested'
                ? 'The booking remains pending until the trainer approves it.'
                : 'This is an automated PLYRLab booking notification.');
    }

}
