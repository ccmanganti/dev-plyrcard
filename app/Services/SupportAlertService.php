<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;

class SupportAlertService
{
    public function sendSupportTicket(SupportTicket $ticket): array
    {
        $ticket->loadMissing('user');

        $requester = $ticket->user;
        $requesterName = $requester
            ? trim(collect([$requester->first_name, $requester->last_name])->filter()->implode(' '))
            : '';
        $requesterName = $requesterName !== '' ? $requesterName : 'PLYRCARD user';

        $subject = '[PLYRCARD Support ' . $ticket->ticket_number . '] ' . $ticket->categoryLabel();
        $adminUrl = rtrim((string) config('app.url'), '/') . '/admin/support-tickets/' . $ticket->getKey() . '/edit';

        $html = $this->layout(
            heading: 'New support ticket',
            intro: 'A PLYRCARD user submitted a new support request.',
            rows: [
                'Ticket' => $ticket->ticket_number,
                'From' => $requesterName,
                'Email' => $ticket->email,
                'Concern' => $ticket->categoryLabel(),
                'Source' => str_replace('_', ' ', ucfirst($ticket->source)),
                'Submitted' => optional($ticket->created_at)->format('M j, Y g:i A') ?: now()->format('M j, Y g:i A'),
            ],
            message: $ticket->message,
            actionLabel: 'Open Support Ticket',
            actionUrl: $adminUrl,
        );

        return $this->sendToAdmins($subject, $html, $ticket->email, 'support_ticket', [
            'ticket_id' => $ticket->getKey(),
            'ticket_number' => $ticket->ticket_number,
            'user_id' => $ticket->user_id,
        ]);
    }

    public function sendDowngradeRequest(User $user, string $currentRole, array $billingContext = []): array
    {
        $name = trim(collect([$user->first_name, $user->last_name])->filter()->implode(' ')) ?: ($user->email ?: 'PLYRCARD user');
        $email = filter_var((string) $user->email, FILTER_VALIDATE_EMAIL) ? (string) $user->email : null;
        $subject = '[PLYRCARD Downgrade Request] ' . $name . ' - ' . $currentRole . ' to Free';

        $rows = [
            'User' => $name,
            'Email' => $email ?: 'Not available',
            'Current role' => $currentRole,
            'Requested plan' => 'Free',
            'User ID' => (string) $user->getKey(),
            'Requested' => now()->format('M j, Y g:i A'),
        ];

        if (filled($billingContext['subscription_id'] ?? null)) {
            $rows['Subscription ID'] = (string) $billingContext['subscription_id'];
        }
        if (filled($billingContext['subscriber_contact_id'] ?? null)) {
            $rows['Subscriber Contact ID'] = (string) $billingContext['subscriber_contact_id'];
        }

        $html = $this->layout(
            heading: 'Plan downgrade requested',
            intro: 'A paid PLYRCARD user requested to move to the Free plan. Their current paid access should remain until cancellation is confirmed.',
            rows: $rows,
            message: 'Please review the billing account, complete the subscription cancellation when appropriate, and then update the user role to Free.',
            actionLabel: 'Open User Management',
            actionUrl: rtrim((string) config('app.url'), '/') . '/admin/users/users',
        );

        return $this->sendToAdmins($subject, $html, $email, 'downgrade_request', [
            'user_id' => $user->getKey(),
            'current_role' => $currentRole,
        ]);
    }

    public function adminRecipients(): array
    {
        return collect((array) config('plyrcard-support.admin_emails', []))
            ->map(fn ($email) => $this->sanitizeEmail((string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function sendToAdmins(string $subject, string $html, ?string $replyTo, string $purpose, array $context = []): array
    {
        $recipients = $this->adminRecipients();
        $sender = $this->sanitizeEmail(PlyrcardMailSender::email());
        $replyTo = $this->sanitizeEmail((string) $replyTo) ?: $sender;

        if ($recipients === []) {
            return ['success' => false, 'sent' => 0, 'recipients' => [], 'error' => 'No admin alert recipients are configured.'];
        }

        if (! $sender || ! function_exists('mail') || ! is_callable('mail')) {
            return ['success' => false, 'sent' => 0, 'recipients' => $recipients, 'error' => 'The server mail transport is unavailable.'];
        }

        $subject = $this->sanitizeHeader($subject);
        $plain = $this->plainText($html);
        $sent = [];
        $failed = [];

        foreach ($recipients as $recipient) {
            $boundary = 'plyrcard_alert_' . bin2hex(random_bytes(12));
            $headers = [
                'MIME-Version: 1.0',
                'From: ' . PlyrcardMailSender::name() . ' <' . $sender . '>',
                'Reply-To: ' . $replyTo,
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                'X-Mailer: PLYRCARD PHP/' . PHP_VERSION,
            ];
            $message = '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . $plain . "\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . $html . "\r\n"
                . '--' . $boundary . '--';

            $ok = false;
            try {
                $ok = @mail($recipient, $subject, $message, implode("\r\n", $headers), '-f' . $sender);
                if (! $ok) {
                    $ok = @mail($recipient, $subject, $message, implode("\r\n", $headers));
                }
            } catch (\Throwable $exception) {
                Log::warning('PLYRCARD internal alert email threw an exception.', array_merge($context, [
                    'purpose' => $purpose,
                    'recipient' => $recipient,
                    'error' => $exception->getMessage(),
                ]));
            }

            if ($ok) {
                $sent[] = $recipient;
            } else {
                $failed[] = $recipient;
            }
        }

        if ($failed !== []) {
            Log::warning('PLYRCARD internal alert email was not accepted for all recipients.', array_merge($context, [
                'purpose' => $purpose,
                'failed_recipients' => $failed,
                'sent_recipients' => $sent,
            ]));
        }

        return [
            'success' => $sent !== [],
            'sent' => count($sent),
            'recipients' => $recipients,
            'sent_recipients' => $sent,
            'failed_recipients' => $failed,
            'error' => $sent === [] ? 'The hosting mail server did not accept the alert email.' : null,
        ];
    }

    protected function layout(string $heading, string $intro, array $rows, string $message, string $actionLabel, string $actionUrl): string
    {
        $rowHtml = '';
        foreach ($rows as $label => $value) {
            $rowHtml .= '<tr><td style="padding:7px 12px;color:#667085;font-size:13px;width:150px;vertical-align:top;">' . $this->escape((string) $label) . '</td>'
                . '<td style="padding:7px 12px;color:#101828;font-size:13px;font-weight:600;">' . $this->escape((string) $value) . '</td></tr>';
        }

        return '<!doctype html><html><body style="margin:0;background:#f5f7fa;font-family:Arial,sans-serif;color:#101828;">'
            . '<div style="max-width:680px;margin:0 auto;padding:28px 16px;">'
            . '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">'
            . '<div style="padding:22px 24px;background:#101828;color:#fff;"><div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#ff6338;font-weight:800;">PLYRCARD</div><h1 style="margin:7px 0 0;font-size:22px;">' . $this->escape($heading) . '</h1></div>'
            . '<div style="padding:22px 24px;"><p style="margin:0 0 18px;color:#475467;line-height:1.55;">' . $this->escape($intro) . '</p>'
            . '<table style="width:100%;border-collapse:collapse;background:#f9fafb;border-radius:10px;overflow:hidden;">' . $rowHtml . '</table>'
            . '<div style="margin-top:18px;padding:16px;border:1px solid #e5e7eb;border-radius:10px;white-space:pre-wrap;line-height:1.6;">' . nl2br($this->escape($message)) . '</div>'
            . '<p style="margin:22px 0 0;"><a href="' . $this->escape($actionUrl) . '" style="display:inline-block;background:#ff6338;color:#fff;text-decoration:none;font-weight:800;padding:12px 18px;border-radius:9px;">' . $this->escape($actionLabel) . '</a></p>'
            . '</div></div></div></body></html>';
    }

    protected function sanitizeEmail(string $email): ?string
    {
        $email = strtolower(trim(str_replace(["\r", "\n"], '', $email)));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    protected function plainText(string $html): string
    {
        return trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
