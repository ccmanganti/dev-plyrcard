<?php

namespace App\Services;

use App\Models\User;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;

class AdminSupportEmailService
{
    public function send(
        User $user,
        string $recipient,
        string $subject,
        string $body,
        array $variables = [],
        string $concern = 'custom',
    ): array {
        $recipient = $this->email($recipient);
        $from = $this->email(PlyrcardMailSender::email());
        $replyTo = $this->replyToAddress();
        $subject = $this->header($subject !== '' ? $subject : 'A note from PLYRCARD');

        if (! $recipient) {
            return [
                'requested' => true,
                'success' => false,
                'status' => 'failed',
                'error' => 'The user does not have a valid personal email address.',
            ];
        }

        if (! $from || ! function_exists('mail') || ! is_callable('mail')) {
            return [
                'requested' => true,
                'success' => false,
                'status' => 'failed',
                'error' => 'The PLYRCARD mail transport is unavailable.',
            ];
        }

        $html = $this->render($user, $body, $variables, $concern);
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . PlyrcardMailSender::name() . ' <' . $from . '>',
            'Reply-To: ' . ($replyTo ?: $from),
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Auto-Submitted: auto-generated',
            'X-Auto-Response-Suppress: All',
            'X-Mailer: PLYRCARD PHP/' . PHP_VERSION,
        ];

        $sentWithEnvelope = false;
        $sentWithoutEnvelope = false;
        $firstError = null;
        $fallbackError = null;

        try {
            $sentWithEnvelope = @mail(
                $recipient,
                $subject,
                $html,
                implode("\r\n", $headers),
                '-f' . $from,
            );
            $firstError = error_get_last();
        } catch (\Throwable $exception) {
            $firstError = ['message' => $exception->getMessage()];
        }

        if (! $sentWithEnvelope) {
            try {
                $sentWithoutEnvelope = @mail(
                    $recipient,
                    $subject,
                    $html,
                    implode("\r\n", $headers),
                );
                $fallbackError = error_get_last();
            } catch (\Throwable $exception) {
                $fallbackError = ['message' => $exception->getMessage()];
            }
        }

        $sent = $sentWithEnvelope || $sentWithoutEnvelope;

        if ($sent) {
            Log::info('PLYRCARD admin support email handed to native PHP mail().', [
                'user_id' => $user->getKey(),
                'recipient' => $recipient,
                'from_email' => $from,
                'reply_to' => $replyTo,
                'concern' => $concern,
                'subject' => $subject,
                'used_envelope_sender' => $sentWithEnvelope,
            ]);

            return [
                'requested' => true,
                'success' => true,
                'status' => 'sent',
                'transport' => 'php_mail',
                'recipient' => $recipient,
                'from_email' => $from,
                'reply_to' => $replyTo,
                'used_envelope_sender' => $sentWithEnvelope,
            ];
        }

        $error = $this->mailError($firstError, $fallbackError);

        Log::error('PLYRCARD admin support email send failed.', [
            'user_id' => $user->getKey(),
            'recipient' => $recipient,
            'from_email' => $from,
            'concern' => $concern,
            'subject' => $subject,
            'error' => $error,
        ]);

        return [
            'requested' => true,
            'success' => false,
            'status' => 'failed',
            'transport' => 'php_mail',
            'error' => $error,
        ];
    }

    protected function render(User $user, string $body, array $variables, string $concern): string
    {
        $firstName = $this->e((string) ($user->first_name ?: 'Player'));
        $label = $this->e((string) (config("plyrcard-admin-support.concerns.{$concern}.label") ?: 'Account support'));
        $content = $this->messageHtml($body);

        $adminUrl = $this->safeUrl((string) ($variables['admin_link'] ?? ''));
        $websiteUrl = $this->safeUrl((string) ($variables['website_link'] ?? ''));
        $supportUrl = $this->safeUrl((string) ($variables['support_link'] ?? ''));

        $actions = '';
        if ($adminUrl !== '') {
            $actions .= '<a href="' . $this->e($adminUrl) . '" style="display:inline-block;background:#FF5A3C;color:#0C0E11;text-decoration:none;font-weight:800;padding:13px 22px;border-radius:9px;margin:0 8px 10px 0">Open PLYRCARD</a>';
        }
        if ($websiteUrl !== '') {
            $actions .= '<a href="' . $this->e($websiteUrl) . '" style="display:inline-block;background:#F2F0ED;color:#0C0E11;text-decoration:none;font-weight:800;padding:13px 22px;border-radius:9px;margin:0 0 10px 0">View My Website</a>';
        }

        $supportBlock = $supportUrl !== ''
            ? '<div style="margin-top:18px;padding:16px 18px;border:1px solid #262C33;border-radius:10px;background:#101317;color:#868E99;font-size:13px;line-height:1.6">This is a one-way account message. Please do not reply to this email. If you need help, <a href="' . $this->e($supportUrl) . '" style="color:#FF5A3C;text-decoration:none;font-weight:800">open the PLYRCARD Support form</a>.</div>'
            : '<div style="margin-top:18px;padding:16px 18px;border:1px solid #262C33;border-radius:10px;background:#101317;color:#868E99;font-size:13px;line-height:1.6">This is a one-way account message. Please do not reply to this email. Use the Support form inside PLYRCARD if you need help.</div>';

        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $this->e($label) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11"><tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">'
            . '<tr><td style="padding:0 34px 22px;font-size:19px;font-weight:800">PLYR<span style="color:#FF5A3C">CARD</span></td></tr>'
            . '<tr><td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            . '<tr><td style="padding:38px 34px 0"><div style="font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">' . strtoupper($label) . '</div>'
            . '<h1 style="margin:12px 0 0;font-size:31px;line-height:1.12;color:#F2F0ED">A note for ' . $firstName . '</h1></td></tr>'
            . '<tr><td style="padding:24px 34px 0;font-size:15px;line-height:1.72;color:#D7DBE0">' . $content . '</td></tr>'
            . ($actions !== '' ? '<tr><td style="padding:26px 34px 0">' . $actions . '</td></tr>' : '')
            . '<tr><td style="padding:20px 34px 34px">' . $supportBlock . '</td></tr>'
            . '</table></td></tr>'
            . '<tr><td align="center" style="padding:24px 34px 0;font-size:11.5px;line-height:1.7;color:#5E6670">PLYRCARD Support · Own Your Journey<br>&copy; ' . now()->year . ' PLYRCARD.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function messageHtml(string $body): string
    {
        // Linkify before escaping so query strings such as ?a=1&b=2 are not
        // double-escaped inside href attributes. Every non-URL fragment is escaped.
        $parts = preg_split('~(https?://[^\s<]+)~i', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$body];
        $html = '';

        foreach ($parts as $part) {
            if (! preg_match('~^https?://~i', $part)) {
                $html .= $this->e($part);
                continue;
            }

            $url = $part;
            $trailing = '';
            while ($url !== '' && preg_match('/[\.,;:!\?\)\]]$/', $url)) {
                $trailing = substr($url, -1) . $trailing;
                $url = substr($url, 0, -1);
            }

            $safe = $this->safeUrl($url);
            if ($safe === '') {
                $html .= $this->e($part);
                continue;
            }

            $html .= '<a href="' . $this->e($safe) . '" style="color:#FF5A3C;text-decoration:none;font-weight:700">'
                . $this->e($url) . '</a>' . $this->e($trailing);
        }

        return nl2br($html);
    }

    protected function replyToAddress(): ?string
    {
        $configured = $this->email((string) config('plyrcard-admin-support.no_reply_email'));
        if ($configured) {
            return $configured;
        }

        $sender = $this->email(PlyrcardMailSender::email());
        if (! $sender || ! str_contains($sender, '@')) {
            return $sender;
        }

        [, $domain] = explode('@', $sender, 2);
        return $this->email('no-reply@' . $domain) ?: $sender;
    }

    protected function safeUrl(string $value): string
    {
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
    }

    protected function email(string $value): ?string
    {
        $value = strtolower(trim(str_replace(["\r", "\n"], '', $value)));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    protected function header(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function mailError(?array $first, ?array $fallback): string
    {
        foreach ([$fallback, $first] as $error) {
            $message = trim((string) ($error['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        return 'PHP mail() returned false. The hosting server did not accept the message.';
    }
}
