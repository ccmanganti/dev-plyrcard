<?php

namespace App\Services;

use App\Models\PlyrLabBooking;
use App\Models\User;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlyrLabBookingNotificationService
{
    public function send(PlyrLabBooking $booking, string $event): array
    {
        $booking->loadMissing(['player', 'trainer.user']);

        $recipients = $this->recipients($booking);

        if ($recipients === []) {
            Log::warning('PLYRLab booking notification has no recipients.', [
                'booking_id' => $booking->getKey(),
                'event' => $event,
            ]);

            return [
                'success' => false,
                'all_success' => false,
                'sent' => 0,
                'failed' => 0,
                'results' => [],
                'error' => 'No booking notification recipients were found.',
            ];
        }

        $results = [];

        foreach ($recipients as $email => $recipient) {
            $audience = (string) ($recipient['audience'] ?? 'admin');
            $user = $recipient['user'] ?? null;

            try {
                $subject = $this->subject($event, $audience);
                $html = $this->html($booking, $event, $audience, $user instanceof User ? $user : null);

                $result = app(PlyrcardMailTransport::class)->sendHtml(
                    recipient: $email,
                    subject: $subject,
                    html: $html,
                    replyTo: PlyrcardMailSender::email(),
                    context: [
                        'purpose' => 'plyrlab_' . $event,
                        'booking_id' => $booking->getKey(),
                        'player_user_id' => $booking->player_user_id,
                        'trainer_id' => $booking->trainer_id,
                        'notification_audience' => $audience,
                    ],
                );
            } catch (Throwable $exception) {
                $result = [
                    'success' => false,
                    'recipient' => $email,
                    'error' => $exception->getMessage(),
                ];
            }

            $results[$email] = array_merge($result, [
                'audience' => $audience,
            ]);
        }

        $sent = collect($results)->filter(fn (array $result): bool => (bool) ($result['success'] ?? false))->count();
        $failed = max(0, count($results) - $sent);
        $success = $sent > 0;
        $allSuccess = $failed === 0;

        Log::log($allSuccess ? 'info' : 'warning', $allSuccess
            ? 'PLYRLab booking notifications sent.'
            : 'PLYRLab booking notification delivery was incomplete.', [
            'booking_id' => $booking->getKey(),
            'event' => $event,
            'sent' => $sent,
            'failed' => $failed,
            'recipients' => array_keys($results),
            'results' => $results,
        ]);

        return [
            'success' => $success,
            'all_success' => $allSuccess,
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
            'error' => $success ? null : 'No booking notification email was accepted for delivery.',
        ];
    }

    protected function recipients(PlyrLabBooking $booking): array
    {
        $recipients = [];

        $add = function (?string $email, string $audience, ?User $user = null) use (&$recipients): void {
            $email = $this->sanitizeEmail((string) $email);

            if ($email === null) {
                return;
            }

            // Player/trainer-specific copy wins over generic admin copy if one account
            // happens to use an admin-alert email address as well.
            $priority = ['admin' => 1, 'trainer' => 2, 'player' => 3];
            $current = $recipients[$email]['audience'] ?? null;

            if ($current !== null && ($priority[$current] ?? 0) >= ($priority[$audience] ?? 0)) {
                return;
            }

            $recipients[$email] = [
                'audience' => $audience,
                'user' => $user,
            ];
        };

        $player = $booking->player;
        if ($player instanceof User) {
            $add($this->firstValidEmail([$player->email, $player->personal_email]), 'player', $player);
        }

        $trainerUser = $booking->trainer?->user;
        if ($trainerUser instanceof User) {
            $add($this->firstValidEmail([$trainerUser->email, $trainerUser->personal_email]), 'trainer', $trainerUser);
        }

        // Use the global configured admin-alert list as the primary internal-recipient
        // source so PLYRLab does not depend on a particular Spatie role spelling.
        try {
            foreach (app(SupportAlertService::class)->adminRecipients() as $email) {
                $add($email, 'admin');
            }
        } catch (Throwable $exception) {
            Log::debug('PLYRLab could not read configured admin alert recipients.', [
                'booking_id' => $booking->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        // Also include every Superadmin account. This covers dev/test accounts that are
        // authorized in the panel but are not yet present in System Settings.
        try {
            $admins = User::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                    'Superadmin',
                    'superadmin',
                    'Super Admin',
                ]))
                ->get();

            foreach ($admins as $admin) {
                $add($this->firstValidEmail([$admin->email, $admin->personal_email]), 'admin', $admin);
            }
        } catch (Throwable $exception) {
            Log::debug('PLYRLab could not resolve Superadmin notification recipients.', [
                'booking_id' => $booking->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        return $recipients;
    }

    protected function subject(string $event, string $audience): string
    {
        if ($event === 'booking_requested') {
            return $audience === 'player'
                ? 'Your PLYRLab booking request was sent'
                : 'New PLYRLab booking request';
        }

        return match ($event) {
            'booking_updated' => $audience === 'player'
                ? 'Your PLYRLab booking was updated'
                : 'PLYRLab booking details updated',
            'booking_approved' => 'PLYRLab booking approved',
            'reschedule_requested' => $audience === 'player'
                ? 'Your PLYRLab reschedule request was sent'
                : 'PLYRLab reschedule awaiting approval',
            'reschedule_approved' => 'PLYRLab reschedule approved',
            'reschedule_declined' => 'PLYRLab reschedule declined',
            'booking_cancelled' => 'PLYRLab booking cancelled',
            'booking_declined' => 'PLYRLab booking declined',
            default => 'PLYRLab booking update',
        };
    }

    protected function html(PlyrLabBooking $booking, string $event, string $audience, ?User $recipientUser): string
    {
        $player = $booking->player;
        $trainer = $booking->trainer;
        $trainerUser = $trainer?->user;

        $playerName = trim(collect([
            $player?->first_name,
            $player?->last_name,
        ])->filter()->implode(' ')) ?: 'PLYRCARD player';

        $trainerName = trim(collect([
            $trainerUser?->first_name,
            $trainerUser?->last_name,
        ])->filter()->implode(' '));

        if ($trainerName === '') {
            $trainerName = $trainer?->displayName() ?: 'PLYRLab trainer';
        }

        $displayTime = $event === 'reschedule_requested' && $booking->requested_scheduled_for
            ? $booking->requested_scheduled_for
            : $booking->scheduled_for;

        $when = $displayTime
            ? $displayTime->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A')
            : 'To be confirmed';

        [$heading, $intro, $message, $actionLabel, $actionUrl] = $this->copy(
            $booking,
            $event,
            $audience,
            $playerName,
            $trainerName,
        );

        $rows = [
            'Player' => $playerName,
            'Trainer' => $trainerName,
            'Scheduled for' => $when,
            'Status' => str_replace('_', ' ', ucfirst((string) $booking->status)),
        ];

        if (filled($booking->notes)) {
            $rows['Player note'] = (string) $booking->notes;
        }

        $rowHtml = '';
        foreach ($rows as $label => $value) {
            $rowHtml .= '<tr>'
                . '<td style="padding:8px 0;color:#868E99;font-size:13px;width:140px;vertical-align:top">'
                . $this->escape($label)
                . '</td>'
                . '<td style="padding:8px 0;color:#F2F0ED;font-size:14px;font-weight:700">'
                . $this->escape((string) $value)
                . '</td>'
                . '</tr>';
        }

        $greeting = $audience === 'admin'
            ? 'PLYRLab Admin Update'
            : 'Hi ' . $this->escape($recipientUser?->first_name ?: ($audience === 'trainer' ? 'Trainer' : 'Player')) . ',';

        return '<!doctype html><html><body style="margin:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11">'
            . '<tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">'
            . '<tr><td style="padding:0 34px 22px;font-size:19px;font-weight:800">PLYR<span style="color:#FF5A3C">CARD</span></td></tr>'
            . '<tr><td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            . '<tr><td style="padding:36px 34px 0">'
            . '<div style="font-size:14px;font-weight:700;color:#F2F0ED">' . $greeting . '</div>'
            . '<div style="margin-top:22px;font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">PLYRLab booking</div>'
            . '<h1 style="margin:12px 0 0;font-size:29px;line-height:1.12;color:#F2F0ED">' . $this->escape($heading) . '</h1>'
            . '<p style="margin:14px 0 0;font-size:15px;line-height:1.65;color:#AAB0B7">' . $this->escape($intro) . '</p>'
            . '</td></tr>'
            . '<tr><td style="padding:24px 34px 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rowHtml . '</table></td></tr>'
            . '<tr><td style="padding:20px 34px 0;font-size:14px;line-height:1.65;color:#AAB0B7">' . $this->escape($message) . '</td></tr>'
            . '<tr><td style="padding:26px 34px 34px"><a href="' . $this->escape($actionUrl) . '" style="display:inline-block;padding:14px 24px;background:#FF5A3C;border-radius:10px;color:#fff;text-decoration:none;font-weight:800">' . $this->escape($actionLabel) . '</a></td></tr>'
            . '</table></td></tr>'
            . '<tr><td align="center" style="padding:22px 34px 0;font-size:11.5px;color:#5E6670">PLYRCARD PLYRLab notification<br>&copy; ' . now()->year . ' PLYRCARD.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function copy(
        PlyrLabBooking $booking,
        string $event,
        string $audience,
        string $playerName,
        string $trainerName,
    ): array {
        $playerUrl = rtrim((string) config('app.url'), '/') . '/admin/coach-database/plyrlab';
        $managementUrl = rtrim((string) config('app.url'), '/') . '/admin/plyrlab';

        if ($event === 'booking_requested') {
            if ($audience === 'player') {
                return [
                    'Booking request sent',
                    'Your PLYRLab session request with ' . $trainerName . ' was submitted successfully.',
                    'Your booking is pending until the trainer accepts it. You can still cancel or update the requested schedule while it is pending.',
                    'Open PLYRLab',
                    $playerUrl,
                ];
            }

            if ($audience === 'trainer') {
                return [
                    'New booking request',
                    $playerName . ' requested a PLYRLab session with you.',
                    'Review the requested schedule and approve or decline it before PLYRCard creates or connects the athlete in Plyomat.',
                    'Review Booking',
                    $managementUrl,
                ];
            }

            return [
                'New PLYRLab booking request',
                $playerName . ' submitted a booking request with ' . $trainerName . '.',
                'The booking is pending trainer approval. Open PLYRLab Management to review the request and its current status.',
                'Open PLYRLab Management',
                $managementUrl,
            ];
        }

        if ($event === 'reschedule_requested') {
            if ($audience === 'player') {
                return [
                    'Reschedule request sent',
                    'Your requested new session time was sent to ' . $trainerName . '.',
                    'The existing approved booking remains unchanged until the trainer approves the new time.',
                    'Open PLYRLab',
                    $playerUrl,
                ];
            }

            return [
                'PLYRLab reschedule requested',
                $playerName . ' requested a new session time with ' . $trainerName . '.',
                'Review the requested schedule change in PLYRLab Management.',
                'Review Booking',
                $managementUrl,
            ];
        }

        if ($event === 'booking_updated') {
            if ($audience === 'player') {
                return [
                    'Booking details updated',
                    'Your PLYRLab booking details with ' . $trainerName . ' were updated successfully.',
                    'The trainer and PLYRLab administrators were notified of the change.',
                    'Open PLYRLab',
                    $playerUrl,
                ];
            }

            if ($audience === 'trainer') {
                return [
                    'Booking details updated',
                    $playerName . ' updated the details for their PLYRLab booking with you.',
                    'Review the latest player note and booking details before the session.',
                    'Review Booking',
                    $managementUrl,
                ];
            }

            return [
                'PLYRLab booking details updated',
                $playerName . ' updated their booking details with ' . $trainerName . '.',
                'Open PLYRLab Management to review the latest player note and booking information.',
                'Open PLYRLab Management',
                $managementUrl,
            ];
        }

        $copy = match ($event) {
            'booking_approved' => ['Booking approved', $trainerName . ' approved the PLYRLab booking.', 'The booking can now continue to the Plyomat connection/provisioning step.'],
            'reschedule_approved' => ['Reschedule approved', $trainerName . ' approved the updated PLYRLab schedule.', 'The approved schedule has been updated.'],
            'reschedule_declined' => ['Reschedule declined', $trainerName . ' declined the requested schedule change.', 'The previously approved session time remains unchanged.'],
            'booking_cancelled' => ['Booking cancelled', $playerName . ' cancelled the PLYRLab booking.', 'The booking is no longer active.'],
            'booking_declined' => ['Booking declined', $trainerName . ' declined the pending PLYRLab booking.', 'The player can choose another trainer or submit a new schedule.'],
            default => ['Booking updated', 'The PLYRLab booking was updated.', 'Open PLYRLab to review the latest booking status.'],
        };

        return [
            $copy[0],
            $copy[1],
            $copy[2],
            $audience === 'player' ? 'Open PLYRLab' : 'Open PLYRLab Management',
            $audience === 'player' ? $playerUrl : $managementUrl,
        ];
    }

    protected function firstValidEmail(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $email = $this->sanitizeEmail((string) $candidate);

            if ($email !== null) {
                return $email;
            }
        }

        return null;
    }

    protected function sanitizeEmail(string $email): ?string
    {
        $email = strtolower(trim(str_replace(["\r", "\n"], '', $email)));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
