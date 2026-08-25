<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class LockerRoomReferralEmailService
{
    public function send(User $referrer, string $friendName, string $friendEmail, ?string $personalMessage = null): array
    {
        $recipient = $this->sanitizeEmail($friendEmail);
        $sender = $this->senderEmail();
        $subject = $this->sanitizeHeaderValue(trim((string) ($referrer->first_name ?: 'A PLYRCARD athlete')) . ' invited you to PLYRCARD');

        if ($recipient === null || $sender === null || $subject === '') {
            return [
                'success' => false,
                'error' => 'The referral email address or sender address is invalid.',
            ];
        }

        if (! function_exists('mail') || ! is_callable('mail')) {
            return [
                'success' => false,
                'error' => 'PHP mail() is not available on this server.',
            ];
        }

        $referrerName = trim(collect([$referrer->first_name, $referrer->last_name])->filter()->implode(' ')) ?: 'A PLYRCARD athlete';
        $registrationUrl = rtrim((string) config('app.url'), '/') . '/registration?utm_plan=free&utm_source=locker-room-referral';
        $html = $this->renderEmail($friendName, $referrerName, $registrationUrl, $personalMessage);
        $plain = $this->plainText($friendName, $referrerName, $registrationUrl, $personalMessage);
        $boundary = 'plyrcard_referral_' . bin2hex(random_bytes(16));
        $replyTo = $this->sanitizeEmail((string) $referrer->email) ?: $sender;

        $headers = [
            'MIME-Version: 1.0',
            'From: PLYRCARD <' . $sender . '>',
            'Reply-To: ' . $replyTo,
            'Bcc: support@plyrcard.com',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: PLYRCARD PHP/' . PHP_VERSION,
        ];

        $message = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $plain . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n"
            . '--' . $boundary . '--';

        $headerString = implode("\r\n", $headers);
        $sent = false;
        $error = null;

        try {
            $sent = @mail($recipient, $subject, $message, $headerString, '-f' . $sender);
            $error = error_get_last();
        } catch (\Throwable $exception) {
            $error = ['message' => $exception->getMessage()];
        }

        if (! $sent) {
            try {
                $sent = @mail($recipient, $subject, $message, $headerString);
                $error = error_get_last();
            } catch (\Throwable $exception) {
                $error = ['message' => $exception->getMessage()];
            }
        }

        Log::log($sent ? 'info' : 'warning', 'Locker Room referral email result.', [
            'referrer_user_id' => $referrer->getKey(),
            'recipient' => $recipient,
            'success' => $sent,
            'error' => $sent ? null : ($error['message'] ?? 'mail() returned false.'),
        ]);

        return [
            'success' => $sent,
            'recipient' => $recipient,
            'error' => $sent ? null : ($error['message'] ?? 'The mail server did not accept the invitation email.'),
        ];
    }

    protected function renderEmail(string $friendName, string $referrerName, string $registrationUrl, ?string $personalMessage): string
    {
        $friend = $this->escape(trim($friendName) ?: 'Athlete');
        $referrer = $this->escape($referrerName);
        $url = $this->escape($registrationUrl);
        $message = trim((string) $personalMessage);
        $messageBlock = $message !== ''
            ? '<div style="margin:20px 0 0;padding:14px 16px;border:1px solid #2A3038;border-radius:10px;background:#0F1216;color:#D7DCE2;font-size:14px;line-height:1.6"><strong style="display:block;margin-bottom:6px;color:#F2F0ED">A note from ' . $referrer . '</strong>' . nl2br($this->escape($message)) . '</div>'
            : '';

        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>You are invited to PLYRCARD</title></head>'
            . '<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11"><tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">'
            . '<tr><td style="padding:0 34px 22px;font-size:19px;font-weight:800">PLYR<span style="color:#FF5A3C">CARD</span></td></tr>'
            . '<tr><td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px;padding:38px 34px">'
            . '<div style="font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">Player referral</div>'
            . '<h1 style="margin:12px 0 0;font-size:31px;line-height:1.12;color:#F2F0ED">' . $referrer . ' invited you to PLYRCARD.</h1>'
            . '<p style="margin:14px 0 0;font-size:15px;line-height:1.65;color:#AAB0B7">Hi ' . $friend . ', build your player profile, keep your recruiting information organized, and create a PLYRCARD coaches can view.</p>'
            . $messageBlock
            . '<div style="margin-top:26px"><a href="' . $url . '" style="display:inline-block;padding:14px 24px;background:#FF5A3C;border-radius:10px;color:#ffffff;text-decoration:none;font-weight:800">Create My PLYRCARD</a></div>'
            . '<p style="margin:22px 0 0;font-size:12.5px;line-height:1.6;color:#737C87">You received this because ' . $referrer . ' referred you from their PLYRCARD Locker Room.</p>'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    protected function plainText(string $friendName, string $referrerName, string $registrationUrl, ?string $personalMessage): string
    {
        $lines = [
            'PLYRCARD',
            '',
            $referrerName . ' invited you to PLYRCARD.',
            '',
            'Hi ' . (trim($friendName) ?: 'Athlete') . ',',
            'Build your player profile, keep your recruiting information organized, and create a PLYRCARD coaches can view.',
        ];

        if (trim((string) $personalMessage) !== '') {
            $lines[] = '';
            $lines[] = 'A note from ' . $referrerName . ':';
            $lines[] = trim((string) $personalMessage);
        }

        $lines[] = '';
        $lines[] = 'Create your PLYRCARD: ' . $registrationUrl;

        return implode("\n", $lines);
    }

    protected function senderEmail(): ?string
    {
        if (class_exists(\App\Support\PlyrcardMailSender::class)) {
            return $this->sanitizeEmail(\App\Support\PlyrcardMailSender::email());
        }

        $host = strtolower(trim((string) parse_url((string) config('app.url'), PHP_URL_HOST)));
        return $this->sanitizeEmail(str_contains($host, 'dev.plyrcard.com') ? 'support@dev.plyrcard.com' : 'support@plyrcard.com');
    }

    protected function sanitizeEmail(string $email): ?string
    {
        $email = trim(str_replace(["\r", "\n"], '', $email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function sanitizeHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
